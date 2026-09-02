<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderChangeRequestService
{
    private const EDITABLE_STATUSES = ['pendiente', 'en_preparacion', 'lista'];

    public function __construct(private OperationalNotificationService $notifications) {}

    public function create(Order $order, User $actor, string $type, string $reason, array $desiredLines = [], array $context = []): OrderChangeRequest
    {
        if (! in_array($type, [OrderChangeRequest::TYPE_CANCELLATION, OrderChangeRequest::TYPE_MODIFICATION], true)) {
            throw ValidationException::withMessages(['requestType' => 'El tipo de solicitud no es válido.']);
        }

        $this->assertRequester($actor, $type);
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['requestReason' => 'Explica el motivo con al menos 10 caracteres.']);
        }

        return DB::transaction(function () use ($order, $actor, $type, $reason, $desiredLines, $context): OrderChangeRequest {
            $order = Order::query()->with(['items.addons', 'items.ingredients', 'payments', 'refunds'])->lockForUpdate()->findOrFail($order->id);
            $this->assertActionable($order);

            if (OrderChangeRequest::query()->where('order_id', $order->id)->where('status', OrderChangeRequest::STATUS_PENDING)->exists()) {
                throw ValidationException::withMessages(['requestReason' => 'Esta orden ya tiene una solicitud pendiente de revisión.']);
            }

            $snapshot = $this->snapshot($order);
            $changes = null;
            $proposedTotal = null;

            if ($type === OrderChangeRequest::TYPE_MODIFICATION) {
                [$changes, $proposedTotal] = $this->buildChanges($order, $desiredLines);
            }

            $changes = array_merge($changes ?? [], [
                'request_context' => $this->buildRequestContext($context, $type, $order, $proposedTotal),
            ]);

            $request = OrderChangeRequest::create([
                'order_id' => $order->id,
                'requested_by' => $actor->id,
                'type' => $type,
                'status' => OrderChangeRequest::STATUS_PENDING,
                'reason' => trim($reason),
                'original_snapshot' => $snapshot,
                'proposed_changes' => $changes,
                'original_total' => $order->total,
                'proposed_total' => $proposedTotal,
            ]);

            DB::afterCommit(fn () => $this->notifications->orderChangeRequested($request->fresh(['order', 'requester'])));

            return $request;
        });
    }

    private function buildRequestContext(array $context, string $type, Order $order, ?float $proposedTotal): array
    {
        $allowedScopes = $type === OrderChangeRequest::TYPE_CANCELLATION
            ? ['full']
            : ['partial', 'adjustment'];

        $result = [
            'scope' => in_array($context['scope'] ?? null, $allowedScopes, true)
                ? $context['scope']
                : ($type === OrderChangeRequest::TYPE_CANCELLATION ? 'full' : 'adjustment'),
            'reason_code' => mb_substr((string) ($context['reason_code'] ?? 'other'), 0, 60),
            'customer_confirmed' => in_array($context['customer_confirmed'] ?? null, ['yes', 'no', 'not_applicable'], true)
                ? $context['customer_confirmed']
                : 'not_applicable',
            'preparation_stage' => in_array($context['preparation_stage'] ?? null, ['not_started', 'in_progress', 'ready', 'unknown'], true)
                ? $context['preparation_stage']
                : 'unknown',
            'source' => in_array($context['source'] ?? null, ['list', 'detail'], true) ? $context['source'] : 'detail',
            'payment_state' => $this->isPaidOrder($order) ? 'paid' : 'unpaid',
        ];

        if (! $this->isPaidOrder($order)) {
            return $result;
        }

        $disposition = $context['inventory_disposition'] ?? null;
        if (! in_array($disposition, ['restock', 'waste', 'not_applicable'], true)) {
            throw ValidationException::withMessages(['inventoryDisposition' => 'Indica qué ocurrirá con los productos cancelados.']);
        }

        $targetTotal = $type === OrderChangeRequest::TYPE_CANCELLATION ? 0.0 : (float) $proposedTotal;
        $refundAmount = round((float) $order->total - $targetTotal, 2);
        if ($refundAmount < -0.009) {
            throw ValidationException::withMessages([
                'requestItems' => 'Una orden pagada no puede aumentar su total desde este flujo. Genera una orden adicional para cobrar la diferencia.',
            ]);
        }

        $available = $this->availableRefundsByMethod($order);
        if ($refundAmount > round(array_sum($available), 2) + 0.009) {
            throw ValidationException::withMessages(['requestItems' => 'El importe solicitado supera el saldo pagado disponible para devolución.']);
        }

        return $result + [
            'inventory_disposition' => $disposition,
            'refund_amount' => max(0, $refundAmount),
            'refund_allocations' => $this->allocateRefund($available, max(0, $refundAmount)),
        ];
    }

    public function approve(OrderChangeRequest $changeRequest, User $reviewer, ?string $notes = null, array $financial = []): OrderChangeRequest
    {
        $this->assertReviewer($reviewer);

        return DB::transaction(function () use ($changeRequest, $reviewer, $notes, $financial): OrderChangeRequest {
            $request = OrderChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            $this->assertPending($request);
            $order = Order::query()->with(['items', 'payments', 'refunds'])->lockForUpdate()->findOrFail($request->order_id);
            $this->assertActionable($order);
            $wasPaid = $this->isPaidOrder($order);

            if ($wasPaid && ! $reviewer->can('anular pagos')) {
                throw new AuthorizationException('No tienes permiso para registrar devoluciones de pagos.');
            }

            if ($request->type === OrderChangeRequest::TYPE_CANCELLATION) {
                $previousStatus = $order->status;
                $order->update([
                    'status' => 'cancelada',
                    'cancelled_by' => $reviewer->id,
                    'cancellation_reason' => $request->reason,
                    'cancelled_at' => now(),
                ]);
                DB::afterCommit(fn () => $this->notifications->orderStatusChanged($order->fresh(), $previousStatus));
            } else {
                $this->applyModification($order, $request, $reviewer);
            }

            if ($wasPaid) {
                $this->recordRefund($order, $request, $reviewer, $financial);
            }

            $request->update([
                'status' => OrderChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewer_notes' => filled($notes) ? trim($notes) : null,
                'reviewed_at' => now(),
                'applied_at' => now(),
            ]);

            return $request->fresh(['order', 'requester', 'reviewer', 'refund']);
        });
    }

    private function recordRefund(Order $order, OrderChangeRequest $request, User $reviewer, array $financial): void
    {
        $context = data_get($request->proposed_changes, 'request_context', []);
        $amount = round((float) data_get($context, 'refund_amount', 0), 2);
        if ($amount <= 0) {
            return;
        }

        $allocations = collect(data_get($context, 'refund_allocations', []))
            ->map(fn ($value) => round((float) $value, 2))
            ->filter(fn ($value) => $value > 0)
            ->all();

        $available = $this->availableRefundsByMethod($order);
        if (round(array_sum($allocations), 2) !== $amount) {
            throw ValidationException::withMessages(['review' => 'La distribución del reembolso ya no coincide con el importe autorizado.']);
        }
        foreach ($allocations as $method => $allocation) {
            if ($allocation > round((float) ($available[$method] ?? 0), 2) + 0.009) {
                throw ValidationException::withMessages(['review' => 'El saldo reembolsable cambió. Rechaza esta solicitud y genera una nueva.']);
            }
        }
        $requiresReference = collect($allocations)->except(['efectivo', 'contra_entrega'])->sum() > 0;
        $reference = trim((string) ($financial['external_reference'] ?? ''));
        if ($requiresReference && mb_strlen($reference) < 4) {
            throw ValidationException::withMessages(['refundReference' => 'Registra la referencia de devolución de tarjeta o transferencia.']);
        }

        $refund = OrderRefund::create([
            'order_id' => $order->id,
            'order_change_request_id' => $request->id,
            'cash_register_id' => $order->cash_register_id,
            'processed_by' => $reviewer->id,
            'type' => $request->type === OrderChangeRequest::TYPE_CANCELLATION ? 'total' : 'partial',
            'amount' => $amount,
            'allocations' => $allocations,
            'external_reference' => $reference ?: null,
            'inventory_disposition' => data_get($context, 'inventory_disposition', 'not_applicable'),
            'status' => 'recorded',
            'reason' => $request->reason,
            'processed_at' => now(),
        ]);

        $cashAmount = round((float) collect($allocations)->only(['efectivo', 'contra_entrega'])->sum(), 2);
        if ($cashAmount <= 0) {
            return;
        }

        $movement = CashMovement::create([
            'cash_register_id' => $order->cash_register_id,
            'created_by' => $reviewer->id,
            'type' => 'expense',
            'amount' => $cashAmount,
            'category' => 'reembolso_orden',
            'description' => "Reembolso de orden #{$order->display_folio}",
            'payment_method' => 'cash',
            'notes' => "Solicitud #{$request->id}: {$request->reason}",
        ]);
        $refund->update(['cash_movement_id' => $movement->id]);
    }

    public function reject(OrderChangeRequest $changeRequest, User $reviewer, string $notes): OrderChangeRequest
    {
        $this->assertReviewer($reviewer);
        if (mb_strlen(trim($notes)) < 5) {
            throw ValidationException::withMessages(['reviewNotes' => 'Indica el motivo del rechazo.']);
        }

        return DB::transaction(function () use ($changeRequest, $reviewer, $notes): OrderChangeRequest {
            $request = OrderChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            $this->assertPending($request);
            $request->update([
                'status' => OrderChangeRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewer_notes' => trim($notes),
                'reviewed_at' => now(),
            ]);

            return $request->fresh(['order', 'requester', 'reviewer', 'refund']);
        });
    }

    private function buildChanges(Order $order, array $desiredLines): array
    {
        $activeItems = $order->items->where('is_cancelled', false)->keyBy('id');
        $existingLines = collect($desiredLines)->where('kind', 'existing')->keyBy(fn (array $line) => (int) ($line['order_item_id'] ?? 0));

        if ($existingLines->keys()->sort()->values()->all() !== $activeItems->keys()->map(fn ($id) => (int) $id)->sort()->values()->all()) {
            throw ValidationException::withMessages(['requestItems' => 'La orden cambió mientras preparabas la solicitud. Vuelve a abrirla.']);
        }

        $changes = [];
        $proposedTotal = 0.0;

        foreach ($activeItems as $item) {
            $quantity = max(0, min(99, (int) ($existingLines[$item->id]['quantity'] ?? 0)));
            $unitSubtotal = round((float) $item->subtotal / max(1, (int) $item->quantity), 2);
            $newSubtotal = round($unitSubtotal * $quantity, 2);
            $proposedTotal += $newSubtotal;

            if ($quantity !== (int) $item->quantity) {
                $changes[] = [
                    'action' => $quantity === 0 ? 'remove' : 'update',
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'from_quantity' => (int) $item->quantity,
                    'to_quantity' => $quantity,
                    'unit_subtotal' => $unitSubtotal,
                    'before_subtotal' => (float) $item->subtotal,
                    'after_subtotal' => $newSubtotal,
                ];
            }
        }

        foreach (collect($desiredLines)->where('kind', 'new') as $line) {
            $quantity = max(0, min(99, (int) ($line['quantity'] ?? 0)));
            if ($quantity === 0) {
                continue;
            }

            $product = Product::query()->where('is_active', true)->find($line['product_id'] ?? 0);
            if (! $product) {
                throw ValidationException::withMessages(['productSearch' => 'Uno de los productos agregados ya no está disponible.']);
            }

            $subtotal = round((float) $product->price * $quantity, 2);
            $proposedTotal += $subtotal;
            $changes[] = [
                'action' => 'add',
                'product_id' => $product->id,
                'product_name' => $product->name,
                'from_quantity' => 0,
                'to_quantity' => $quantity,
                'unit_subtotal' => (float) $product->price,
                'before_subtotal' => 0,
                'after_subtotal' => $subtotal,
            ];
        }

        if ($proposedTotal <= 0) {
            throw ValidationException::withMessages(['requestItems' => 'La modificación debe conservar al menos un producto en la orden.']);
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['requestItems' => 'Modifica una cantidad, retira un producto o agrega uno nuevo.']);
        }

        return [['items' => $changes], round($proposedTotal, 2)];
    }

    private function applyModification(Order $order, OrderChangeRequest $request, User $reviewer): void
    {
        foreach (data_get($request->proposed_changes, 'items', []) as $change) {
            if ($change['action'] === 'add') {
                $product = Product::query()->where('is_active', true)->find($change['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages(['review' => "El producto {$change['product_name']} ya no está disponible."]);
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'quantity' => $change['to_quantity'],
                    'subtotal' => round((float) $product->price * (int) $change['to_quantity'], 2),
                    'promotion_discount' => 0,
                ]);

                continue;
            }

            $item = OrderItem::query()->where('order_id', $order->id)->lockForUpdate()->find($change['order_item_id']);
            if (! $item || $item->is_cancelled || (int) $item->quantity !== (int) $change['from_quantity'] || round((float) $item->subtotal, 2) !== round((float) $change['before_subtotal'], 2)) {
                throw ValidationException::withMessages(['review' => 'La orden cambió después de la solicitud. Recházala y genera una nueva.']);
            }

            if ($change['action'] === 'remove') {
                $item->update(['is_cancelled' => true, 'cancelled_by' => $reviewer->id, 'cancelled_at' => now()]);

                continue;
            }

            $factor = (int) $change['to_quantity'] / max(1, (int) $change['from_quantity']);
            $item->update([
                'quantity' => (int) $change['to_quantity'],
                'subtotal' => round((float) $change['after_subtotal'], 2),
                'promotion_discount' => round((float) $item->promotion_discount * $factor, 2),
            ]);
        }

        $subtotal = (float) OrderItem::query()->where('order_id', $order->id)->where('is_cancelled', false)->sum('subtotal');
        $order->update(['subtotal' => round($subtotal, 2), 'total' => round($subtotal, 2)]);

        if ($order->mesa_service_id) {
            $order->mesaService()->update([
                'total_snapshot' => round((float) Order::query()
                    ->where('mesa_service_id', $order->mesa_service_id)
                    ->sum('total'), 2),
            ]);
        }
    }

    private function snapshot(Order $order): array
    {
        return [
            'order_status' => $order->status,
            'order_updated_at' => $order->updated_at?->toIso8601String(),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'is_cancelled' => (bool) $item->is_cancelled,
            ])->values()->all(),
            'payments' => $order->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'transfer_reference' => $payment->transfer_reference,
            ])->values()->all(),
            'prior_refunds' => $order->refunds->map(fn (OrderRefund $refund) => [
                'id' => $refund->id,
                'amount' => (float) $refund->amount,
                'allocations' => $refund->allocations,
                'processed_at' => $refund->processed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function availableRefundsByMethod(Order $order): array
    {
        $paid = $order->payments
            ->groupBy('method')
            ->map(fn ($payments) => round((float) $payments->sum('amount'), 2));
        $refunded = collect();
        foreach ($order->refunds as $refund) {
            foreach ($refund->allocations ?? [] as $method => $amount) {
                $refunded->put($method, round((float) $refunded->get($method, 0) + (float) $amount, 2));
            }
        }

        return $paid->mapWithKeys(fn ($amount, $method) => [
            $method => max(0, round($amount - (float) ($refunded[$method] ?? 0), 2)),
        ])->filter(fn ($amount) => $amount > 0)->all();
    }

    private function allocateRefund(array $available, float $amount): array
    {
        if ($amount <= 0) {
            return [];
        }

        $total = array_sum($available);
        $remaining = round($amount, 2);
        $allocations = [];
        $methods = array_keys($available);
        foreach ($methods as $index => $method) {
            $allocation = $index === array_key_last($methods)
                ? $remaining
                : min((float) $available[$method], round($amount * ((float) $available[$method] / max(0.01, $total)), 2));
            $allocation = max(0, min($allocation, (float) $available[$method]));
            if ($allocation > 0) {
                $allocations[$method] = round($allocation, 2);
                $remaining = round($remaining - $allocation, 2);
            }
        }

        if ($remaining > 0.009) {
            foreach ($methods as $method) {
                $capacity = round((float) $available[$method] - (float) ($allocations[$method] ?? 0), 2);
                $extra = min($capacity, $remaining);
                if ($extra > 0) {
                    $allocations[$method] = round((float) ($allocations[$method] ?? 0) + $extra, 2);
                    $remaining = round($remaining - $extra, 2);
                }
            }
        }

        return $allocations;
    }

    private function isPaidOrder(Order $order): bool
    {
        return $order->status === 'pagada' && $order->payments->isNotEmpty();
    }

    private function assertActionable(Order $order): void
    {
        $isUnpaidActive = in_array($order->status, self::EDITABLE_STATUSES, true) && $order->payments->isEmpty();
        if (! $isUnpaidActive && ! $this->isPaidOrder($order)) {
            throw ValidationException::withMessages(['requestReason' => 'Solo se admiten órdenes activas sin pago u órdenes pagadas con saldo reembolsable.']);
        }
    }

    private function assertPending(OrderChangeRequest $request): void
    {
        if ($request->status !== OrderChangeRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['review' => 'Esta solicitud ya fue atendida.']);
        }
    }

    private function assertReviewer(User $reviewer): void
    {
        if (! $reviewer->hasAnyRole(['owner', 'super-admin']) || ! $reviewer->can('revisar solicitudes de ordenes')) {
            throw new AuthorizationException('Solo owner o super-admin pueden resolver solicitudes de órdenes.');
        }
    }

    private function assertRequester(User $actor, string $type): void
    {
        $permission = $type === OrderChangeRequest::TYPE_CANCELLATION
            ? 'solicitar cancelacion de ordenes'
            : 'solicitar modificacion de ordenes';

        if (! $actor->can($permission)) {
            throw new AuthorizationException('No tienes permiso para crear esta solicitud.');
        }
    }
}
