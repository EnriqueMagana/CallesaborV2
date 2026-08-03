<?php

use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Services\MesaServiceManager;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $manager = app(MesaServiceManager::class);

        CashRegister::query()
            ->where('is_open', true)
            ->each(function (CashRegister $register) use ($manager): void {
                Order::query()
                    ->where('cash_register_id', $register->id)
                    ->where('type', 'mesa')
                    ->whereNull('mesa_service_id')
                    ->whereNotNull('mesa_id')
                    ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                    ->oldest('id')
                    ->get()
                    ->each(function (Order $order) use ($manager, $register): void {
                        $mesa = Mesa::find($order->mesa_id);
                        if (! $mesa) {
                            return;
                        }

                        $service = $manager->resolveOrCreate(
                            $mesa,
                            $register,
                            $order->served_by,
                            $order->source === 'kiosk' ? 'kiosk' : 'waiter',
                            $order->kioskTerminal,
                        );
                        $memberIds = $service->mesas()->pluck('mesas.id')->all();

                        Order::query()
                            ->where('cash_register_id', $register->id)
                            ->whereIn('mesa_id', $memberIds)
                            ->whereNull('mesa_service_id')
                            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                            ->update(['mesa_service_id' => $service->id]);

                        MesaAssignment::query()
                            ->whereIn('mesa_id', $memberIds)
                            ->whereNull('mesa_service_id')
                            ->whereNull('released_at')
                            ->update(['mesa_service_id' => $service->id]);

                        MesaSplit::query()
                            ->whereIn('mesa_id', $memberIds)
                            ->whereNull('mesa_service_id')
                            ->whereIn('status', ['pendiente', 'parcial'])
                            ->update(['mesa_service_id' => $service->id]);

                        if (Mesa::whereIn('id', $memberIds)->where('status', 'en_cuenta')->exists()) {
                            $service->update([
                                'status' => 'en_cuenta',
                                'in_account_at' => $service->in_account_at ?? now(),
                            ]);
                        }
                    });
            });
    }

    public function down(): void
    {
        // La instantánea histórica no se elimina en una reversión de datos.
    }
};
