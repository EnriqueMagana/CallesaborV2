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
        if (! in_array($type, [
            OrderChangeRequest::TYPE_CANCELLATION,
            OrderChangeRequest::TYPE_MODIFICATION,
            OrderChangeRequest::TYPE_PAYMENT_CHANGE,
            OrderChangeRequest::TYPE_ADDRESS_CHANGE,
        ], true)) {
            throw ValidationException::withMessages(['requestType' => 'El tipo de solicitud no es válido.']);
        }

        $this->assertRequester($actor, $type);
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['requestReason' => 'Explica el motivo con al menos 10 caracteres.']);
        }

        return DB::transaction(function () use ($order, $actor, $type, $reason, $desiredLines, $context): OrderChangeRequest {
            $order = Order::query()->with(['items.addons', 'items.ingredients', 'payments', 'refunds', 'customer', 'deliveryAssignment'])->lockForUpdate()->findOrFail($order->id);
            $this->assertActionable($order, $type);

            if (OrderChangeRequest::query()->where('order_id', $order->id)->where('status', OrderChangeRequest::STATUS_PENDING)->exists()) {
                throw ValidationException::withMessages(['requestReason' => 'Esta orden ya tiene una solicitud pendiente de revisión.']);
            }

            $snapshot = $this->snapshot($order);
            $changes = null;
            $proposedTotal = null;

            if ($type === OrderChangeRequest::TYPE_MODIFICATION) {
                [$changes, $proposedTotal] = $this->buildChanges($order, $desiredLines);
            } elseif ($type === OrderChangeRequest::TYPE_PAYMENT_CHANGE) {
                $changes = ['payment_change' => $this->buildPaymentChange($order, $context)];
                $proposedTotal = (float) $order->total;
            } elseif ($type === OrderChangeRequest::TYPE_ADDRESS_CHANGE) {
                $changes = ['address_change' => $this->buildAddressChange($order, $context)];
                $proposedTotal = (float) $order->total;
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
        $allowedScopes = match ($type) {
            OrderChangeRequest::TYPE_CANCELLATION => ['full'],
            OrderChangeRequest::TYPE_MODIFICATION => ['partial', 'adjustment'],
            OrderChangeRequest::TYPE_PAYMENT_CHANGE => ['payment'],
            OrderChangeRequest::TYPE_ADDRESS_CHANGE => ['address'],
            default => [],
        };

        $defaultScope = match ($type) {
            OrderChangeRequest::TYPE_CANCELLATION => 'full',
            OrderChangeRequest::TYPE_PAYMENT_CHANGE => 'payment',
            OrderChangeRequest::TYPE_ADDRESS_CHANGE => 'address',
            default => 'adjustment',
        };

        $result = [
            'scope' => in_array($context['scope'] ?? null, $allowedScopes, true)
                ? $context['scope']
                : $defaultScope,
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

        if (! in_array($type, [OrderChangeRequest::TYPE_CANCELLATION, OrderChangeRequest::TYPE_MODIFICATION], true)
            || ! $this->isPaidOrder($order)) {
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
            $order = Order::query()->with(['items', 'payments', 'refunds', 'customer', 'deliveryAssignment'])->lockForUpdate()->findOrFail($request->order_id);
            $this->assertActionable($order, $request->type);
            $wasPaid = $this->isPaidOrder($order);

            if ($wasPaid
                && in_array($request->type, [OrderChangeRequest::TYPE_CANCELLATION, OrderChangeRequest::TYPE_MODIFICATION], true)
                && ! $reviewer->can('anular pagos')) {
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
            } elseif ($request->type === OrderChangeRequest::TYPE_MODIFICATION) {
                $this->applyModification($order, $request, $reviewer);
            } elseif ($request->type === OrderChangeRequest::TYPE_PAYMENT_CHANGE) {
                $this->applyPaymentChange($order, $request);
            } else {
                $this->applyAddressChange($order, $request);
            }

            if ($wasPaid && in_array($request->type, [OrderChangeRequest::TYPE_CANCELLATION, OrderChangeRequest::TYPE_MODIFICATION], true)) {
                $this->recordRefund($order, $request, $reviewer, $financial);
            }

            $request->update([
                'status' => OrderChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewer_notes' => filled($notes) ? trim($notes) : null,
                'reviewed_at' => now(),
                'applied_at' => now(),
            ]);

            DB::afterCommit(fn () => $this->notifications->orderChangeResolved($request->fresh(['order', 'requester', 'reviewer'])));

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
            'description' => "Reembolso de orden {$order->display_folio}",
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

            DB::afterCommit(fn () => $this->notifications->orderChangeResolved($request->fresh(['order', 'requester', 'reviewer'])));

            return $request->fresh(['order', 'requester', 'reviewer', 'refund']);
        });
    }

    private function buildPaymentChange(Order $order, array $context): array
    {
        if ($order->type !== 'delivery' || ! $this->isPaidOrder($order)) {
            throw ValidationException::withMessages([
                'newPaymentMethod' => 'El cambio de método solo está disponible para delivery pagado en la caja actual.',
            ]);
        }

        if ($order->refunds->isNotEmpty() || $order->payments->count() !== 1) {
            throw ValidationException::withMessages([
                'newPaymentMethod' => 'Los pagos mixtos o con devoluciones requieren una revisión contable manual.',
            ]);
        }

        if (($context['previous_payment_received'] ?? null) !== 'no') {
            throw ValidationException::withMessages([
                'previousPaymentReceived' => 'Si el pago anterior sí ingresó, no debe reclasificarse: registra una devolución antes de aceptar otro pago.',
            ]);
        }

        $payment = $order->payments->first();
        $requestedMethod = (string) ($context['new_payment_method'] ?? '');
        $newMethod = match ($requestedMethod) {
            'cash' => 'efectivo',
            'card' => 'tarjeta',
            'transfer' => 'transferencia',
            default => throw ValidationException::withMessages(['newPaymentMethod' => 'Selecciona el nuevo método de pago.']),
        };
        $newDeliveryMethod = match ($requestedMethod) {
            'cash' => 'contra_entrega',
            'card' => 'tarjeta',
            'transfer' => 'transferencia',
        };

        if ($newMethod === $payment->method) {
            throw ValidationException::withMessages(['newPaymentMethod' => 'El nuevo método debe ser diferente al registrado.']);
        }

        $amount = round((float) $payment->amount, 2);
        $received = null;
        $change = null;
        $last4 = null;
        $reference = null;

        if ($newMethod === 'efectivo') {
            $received = round((float) ($context['cash_received'] ?? 0), 2);
            if ($received < $amount) {
                throw ValidationException::withMessages(['paymentCashReceived' => 'El efectivo recibido debe cubrir el total pagado.']);
            }
            $change = round($received - $amount, 2);
        } elseif ($newMethod === 'tarjeta') {
            $last4 = preg_replace('/\D+/', '', (string) ($context['card_last4'] ?? ''));
            if (strlen($last4) !== 4) {
                throw ValidationException::withMessages(['paymentCardLast4' => 'Captura los últimos 4 dígitos de la tarjeta.']);
            }
        } else {
            $reference = trim((string) ($context['transfer_reference'] ?? ''));
            if (mb_strlen($reference) < 4) {
                throw ValidationException::withMessages(['paymentTransferReference' => 'Captura la referencia de la transferencia.']);
            }
        }

        return [
            'payment_id' => $payment->id,
            'amount' => $amount,
            'before' => [
                'delivery_method' => $order->delivery_method,
                'method' => $payment->method,
                'received_amount' => $payment->received_amount !== null ? (float) $payment->received_amount : null,
                'change_amount' => $payment->change_amount !== null ? (float) $payment->change_amount : null,
                'card_last4' => $payment->card_last4,
                'transfer_reference' => $payment->transfer_reference,
            ],
            'after' => [
                'delivery_method' => $newDeliveryMethod,
                'method' => $newMethod,
                'received_amount' => $received,
                'change_amount' => $change,
                'card_last4' => $last4,
                'transfer_reference' => $reference,
            ],
        ];
    }

    private function buildAddressChange(Order $order, array $context): array
    {
        if ($order->type !== 'delivery') {
            throw ValidationException::withMessages(['newAddress' => 'La dirección solo puede cambiarse en órdenes delivery.']);
        }

        if ($order->deliveryAssignment?->status === 'entregado') {
            throw ValidationException::withMessages(['newAddress' => 'La entrega ya fue completada y no admite cambio de dirección.']);
        }

        $after = [
            'address' => trim((string) ($context['new_address'] ?? '')),
            'neighborhood' => trim((string) ($context['new_neighborhood'] ?? '')),
            'references' => trim((string) ($context['new_references'] ?? '')) ?: null,
            'phone' => trim((string) ($context['new_phone'] ?? '')) ?: null,
        ];
        if (mb_strlen($after['address']) < 5) {
            throw ValidationException::withMessages(['newAddress' => 'Captura una dirección completa.']);
        }
        if (mb_strlen($after['neighborhood']) < 2) {
            throw ValidationException::withMessages(['newNeighborhood' => 'Captura la colonia o zona.']);
        }

        $before = [
            'address' => $order->customer_address,
            'neighborhood' => $order->customer_neighborhood,
            'references' => $order->customer_references,
            'phone' => $order->customer_phone,
        ];
        if ($before === $after) {
            throw ValidationException::withMessages(['newAddress' => 'La nueva dirección y los datos de contacto no contienen cambios.']);
        }

        return [
            'before' => $before,
            'after' => $after,
            'update_customer_profile' => filter_var($context['update_customer_profile'] ?? false, FILTER_VALIDATE_BOOL),
            'delivery_assignment_id' => $order->deliveryAssignment?->id,
            'delivery_status' => $order->deliveryAssignment?->status,
        ];
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

    private function applyPaymentChange(Order $order, OrderChangeRequest $request): void
    {
        $change = data_get($request->proposed_changes, 'payment_change');
        if (! is_array($change)) {
            throw ValidationException::withMessages(['review' => 'La solicitud no contiene un cambio de pago válido.']);
        }

        $payment = $order->payments()->lockForUpdate()->find($change['payment_id'] ?? 0);
        $before = $change['before'] ?? [];
        $after = $change['after'] ?? [];
        if (! $payment
            || $order->payments()->count() !== 1
            || $payment->method !== ($before['method'] ?? null)
            || round((float) $payment->amount, 2) !== round((float) ($change['amount'] ?? 0), 2)
            || $order->delivery_method !== ($before['delivery_method'] ?? null)) {
            throw ValidationException::withMessages(['review' => 'El pago cambió después de crear la solicitud. Recházala y genera una nueva.']);
        }

        $payment->update([
            'method' => $after['method'],
            'received_amount' => $after['received_amount'],
            'change_amount' => $after['change_amount'],
            'card_last4' => $after['card_last4'],
            'transfer_reference' => $after['transfer_reference'],
        ]);
        $order->update(['delivery_method' => $after['delivery_method']]);
    }

    private function applyAddressChange(Order $order, OrderChangeRequest $request): void
    {
        $change = data_get($request->proposed_changes, 'address_change');
        if (! is_array($change)) {
            throw ValidationException::withMessages(['review' => 'La solicitud no contiene un cambio de dirección válido.']);
        }

        $before = $change['before'] ?? [];
        $after = $change['after'] ?? [];
        $current = [
            'address' => $order->customer_address,
            'neighborhood' => $order->customer_neighborhood,
            'references' => $order->customer_references,
            'phone' => $order->customer_phone,
        ];
        if ($current !== $before || $order->deliveryAssignment?->status === 'entregado') {
            throw ValidationException::withMessages(['review' => 'Los datos de entrega cambiaron o el pedido ya fue entregado. Rechaza la solicitud y genera una nueva.']);
        }

        $order->update([
            'customer_address' => $after['address'],
            'customer_neighborhood' => $after['neighborhood'],
            'customer_references' => $after['references'],
            'customer_phone' => $after['phone'],
        ]);

        if (($change['update_customer_profile'] ?? false) && $order->customer) {
            $order->customer->update([
                'address' => $after['address'],
                'neighborhood' => $after['neighborhood'],
                'references' => $after['references'],
                'phone' => $after['phone'] ?: $order->customer->phone,
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
            'delivery' => [
                'method' => $order->delivery_method,
                'address' => $order->customer_address,
                'neighborhood' => $order->customer_neighborhood,
                'references' => $order->customer_references,
                'phone' => $order->customer_phone,
                'assignment_id' => $order->deliveryAssignment?->id,
                'assignment_status' => $order->deliveryAssignment?->status,
            ],
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

    private function assertActionable(Order $order, string $type): void
    {
        if ($type === OrderChangeRequest::TYPE_PAYMENT_CHANGE) {
            if ($order->type !== 'delivery' || ! $this->isPaidOrder($order)) {
                throw ValidationException::withMessages(['requestReason' => 'Solo un delivery pagado admite reclasificación del método de pago.']);
            }

            return;
        }

        if ($type === OrderChangeRequest::TYPE_ADDRESS_CHANGE) {
            if ($order->type !== 'delivery'
                || ! in_array($order->status, ['pendiente', 'en_preparacion', 'lista', 'pagada'], true)
                || $order->deliveryAssignment?->status === 'entregado') {
                throw ValidationException::withMessages(['requestReason' => 'Esta orden ya no admite cambios de dirección.']);
            }

            return;
        }

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
        $permission = match ($type) {
            OrderChangeRequest::TYPE_CANCELLATION => 'solicitar cancelacion de ordenes',
            OrderChangeRequest::TYPE_MODIFICATION => 'solicitar modificacion de ordenes',
            OrderChangeRequest::TYPE_PAYMENT_CHANGE => 'solicitar cambio de metodo de pago',
            OrderChangeRequest::TYPE_ADDRESS_CHANGE => 'solicitar cambio de direccion',
            default => null,
        };

        if (! $permission || ! $actor->can($permission)) {
            throw new AuthorizationException('No tienes permiso para crear esta solicitud.');
        }
    }
}
