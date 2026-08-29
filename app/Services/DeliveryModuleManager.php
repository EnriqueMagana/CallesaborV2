<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\CashRegister;
use App\Models\DeliveryModuleAudit;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryModuleManager
{
    public function __construct(
        private readonly ManualDeliveryAccountingService $accounting,
    ) {}

    public function impact(): array
    {
        $register = CashRegister::query()->where('is_open', true)->latest('opened_at')->first();
        if (! $register) {
            return $this->emptyImpact();
        }

        $deliveries = Order::query()
            ->where('cash_register_id', $register->id)
            ->where('type', 'delivery')
            ->where('status', '!=', 'cancelada');

        $assigned = (clone $deliveries)
            ->where('delivery_flow_mode', 'managed')
            ->whereHas('deliveryAssignment', fn ($query) => $query->where('status', 'asignado'));

        return [
            'cash_register_id' => $register->id,
            'active_orders' => (clone $deliveries)->whereNotIn('status', ['pagada', 'cancelada'])->count(),
            'unassigned_orders' => (clone $deliveries)
                ->where('delivery_flow_mode', 'managed')
                ->whereDoesntHave('deliveryAssignment')
                ->count(),
            'assigned_orders' => (clone $assigned)->where('status', '!=', 'en_reparto')->count(),
            'in_route_orders' => (clone $assigned)->where('status', 'en_reparto')->count(),
            'manual_orders' => (clone $deliveries)->where('delivery_flow_mode', 'manual')->count(),
        ];
    }

    public function setEnabled(bool $enabled, User $actor): array
    {
        // Ensure the singleton row exists before attempting to acquire a lock.
        BusinessSetting::current();

        return DB::transaction(function () use ($enabled, $actor): array {
            $settings = BusinessSetting::query()->lockForUpdate()->firstOrFail();
            $previous = (bool) $settings->delivery_management_enabled;
            $impact = $this->impact();

            if ($previous === $enabled) {
                return ['changed' => false, 'converted_orders' => 0, 'impact' => $impact];
            }

            if (! $enabled && ($impact['assigned_orders'] > 0 || $impact['in_route_orders'] > 0)) {
                throw ValidationException::withMessages([
                    'deliveryModule' => sprintf(
                        'No se puede desactivar: hay %d pedido(s) asignado(s) y %d en ruta. Finalízalos o libera la asignación primero.',
                        $impact['assigned_orders'],
                        $impact['in_route_orders'],
                    ),
                ]);
            }

            $converted = 0;
            if (! $enabled && $impact['cash_register_id']) {
                $orders = Order::query()
                    ->where('cash_register_id', $impact['cash_register_id'])
                    ->where('type', 'delivery')
                    ->where('delivery_flow_mode', 'managed')
                    ->where('status', '!=', 'cancelada')
                    ->whereDoesntHave('deliveryAssignment')
                    ->lockForUpdate()
                    ->get();

                foreach ($orders as $order) {
                    $this->accounting->account($order);
                    $converted++;
                }
            }

            $settings->update([
                'delivery_management_enabled' => $enabled,
                'updated_by' => $actor->id,
            ]);

            DeliveryModuleAudit::create([
                'previous_enabled' => $previous,
                'new_enabled' => $enabled,
                'changed_by' => $actor->id,
                'cash_register_id' => $impact['cash_register_id'],
                'converted_orders' => $converted,
                'impact' => $impact,
                'changed_at' => now(),
            ]);

            return ['changed' => true, 'converted_orders' => $converted, 'impact' => $impact];
        });
    }

    private function emptyImpact(): array
    {
        return [
            'cash_register_id' => null,
            'active_orders' => 0,
            'unassigned_orders' => 0,
            'assigned_orders' => 0,
            'in_route_orders' => 0,
            'manual_orders' => 0,
        ];
    }
}
