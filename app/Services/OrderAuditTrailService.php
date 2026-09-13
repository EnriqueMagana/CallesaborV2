<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

class OrderAuditTrailService
{
    /**
     * @return Collection<int, array{at: mixed, title: string, detail: string, actor: ?string, icon: string, tone: string, changes?: array}>
     */
    public function forOrder(Order $order): Collection
    {
        $events = collect();
        $push = function ($at, string $title, string $detail, ?string $actor, string $icon, string $tone = 'neutral', array $changes = []) use ($events): void {
            if ($at) {
                $events->push(compact('at', 'title', 'detail', 'actor', 'icon', 'tone', 'changes'));
            }
        };

        $push($order->created_at, 'Orden creada', $order->type_label.' · '.$order->display_name, $order->seller?->name, 'bx-receipt', 'primary');
        $push($order->mesaService?->opened_at, 'Servicio de mesa abierto', $order->mesaService?->service_label ?? 'Servicio de mesa', $order->mesaService?->opener?->name, 'bx-table');
        $push($order->mesaService?->in_account_at, 'Cuenta solicitada', 'La mesa pasó a cuenta.', null, 'bx-file');
        $push($order->deliveryAssignment?->assigned_at, 'Entrega asignada', 'Repartidor: '.($order->deliveryAssignment?->driver?->name ?? 'No disponible'), $order->deliveryAssignment?->assignedBy?->name, 'bx-cycling');

        foreach ($order->deliveryAssignment?->events ?? [] as $event) {
            $push($event->created_at, 'Repartidor reasignado', ($event->fromDriver?->name ?? 'Sin asignar').' → '.($event->toDriver?->name ?? 'Sin asignar').($event->reason ? ' · '.$event->reason : ''), $event->actor?->name, 'bx-transfer');
        }

        foreach ($order->payments as $payment) {
            $reference = $payment->card_last4 ? ' · Terminación '.$payment->card_last4 : ($payment->transfer_reference ? ' · Ref. '.$payment->transfer_reference : '');
            $push($payment->created_at, 'Pago registrado', $payment->method_label.' $'.number_format((float) $payment->amount, 2).$reference, $order->seller?->name, $payment->method_icon, 'success');
        }

        foreach ($order->dataChangeAudits as $audit) {
            $changes = $this->changedValues($audit->changes ?? []);
            $push($audit->created_at, 'Datos operativos modificados', count($changes).' campo(s) actualizado(s)', $audit->changedBy?->name, 'bx-edit-alt', 'warning', $changes);
        }

        foreach ($order->changeRequests as $request) {
            $push($request->created_at, $request->type_label.' solicitada', $request->reason, $request->requester?->name, 'bx-git-compare', 'warning');
            $push($request->reviewed_at, $request->type_label.' '.$request->status_label, $request->reviewer_notes ?: 'Sin notas de revisión.', $request->reviewer?->name, $request->status === 'approved' ? 'bx-check-circle' : 'bx-x-circle', $request->status === 'approved' ? 'success' : 'danger');
        }

        foreach ($order->refunds as $refund) {
            $push($refund->processed_at, 'Reembolso registrado', '$'.number_format((float) $refund->amount, 2).' · '.($refund->reason ?: 'Sin motivo'), $refund->processor?->name, 'bx-undo', 'danger');
        }

        $push($order->accounted_at, 'Orden contabilizada', 'Incorporada al corte de caja.', null, 'bx-calculator', 'success');
        $push($order->paid_at, 'Orden pagada', 'Cobro confirmado.', $order->seller?->name, 'bx-check-circle', 'success');
        $push($order->deliveryAssignment?->delivered_at, 'Entrega completada', 'Pedido entregado al cliente.', $order->deliveryAssignment?->deliveredBy?->name, 'bx-package', 'success');
        $push($order->mesaService?->closed_at, 'Servicio de mesa cerrado', $order->mesaService?->close_reason ?: 'Servicio finalizado.', $order->mesaService?->closer?->name, 'bx-lock-alt');
        $push($order->cancelled_at, 'Orden cancelada', $order->cancellation_reason ?: 'Sin motivo registrado.', $order->cancelledBy?->name, 'bx-x-circle', 'danger');

        return $events->sortBy('at')->values();
    }

    private function changedValues(array $snapshot): array
    {
        $before = data_get($snapshot, 'before', []);
        $after = data_get($snapshot, 'after', []);
        $labels = [
            'customer.customer_name' => 'Cliente',
            'customer.customer_phone' => 'Teléfono',
            'customer.customer_address' => 'Dirección',
            'customer.customer_neighborhood' => 'Colonia',
            'customer.customer_references' => 'Referencias',
            'customer.delivery_method' => 'Método de entrega',
        ];
        $changes = collect($labels)->map(function (string $label, string $path) use ($before, $after): ?array {
            $old = data_get($before, $path);
            $new = data_get($after, $path);

            return $old !== $new ? ['field' => $label, 'before' => $old ?: '—', 'after' => $new ?: '—'] : null;
        })->filter()->values();

        $beforePayments = collect(data_get($before, 'payments', []))->keyBy('id');
        foreach (data_get($after, 'payments', []) as $payment) {
            $old = $beforePayments->get($payment['id'] ?? null, []);
            foreach (['method' => 'Método de pago', 'received_amount' => 'Importe recibido', 'card_last4' => 'Terminación de tarjeta', 'transfer_reference' => 'Referencia de transferencia'] as $key => $label) {
                if (($old[$key] ?? null) !== ($payment[$key] ?? null)) {
                    $changes->push(['field' => $label, 'before' => $old[$key] ?? '—', 'after' => $payment[$key] ?? '—']);
                }
            }
        }

        return $changes->all();
    }
}
