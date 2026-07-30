<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\KioskTerminal;
use App\Models\Mesa;
use App\Models\MesaService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MesaServiceManager
{
    public function findActiveForMesa(Mesa $mesa, int $cashRegisterId): ?MesaService
    {
        return MesaService::query()
            ->where('cash_register_id', $cashRegisterId)
            ->active()
            ->where(function ($query) use ($mesa) {
                if ($mesa->mesa_group_id) {
                    $query->where('mesa_group_id', $mesa->mesa_group_id);
                }

                $query->orWhereHas('mesas', fn ($members) => $members->whereKey($mesa->id));
            })
            ->latest('id')
            ->first();
    }

    public function resolveOrCreate(
        Mesa $mesa,
        CashRegister $cashRegister,
        ?int $openedBy,
        string $source = 'waiter',
        ?KioskTerminal $terminal = null,
    ): MesaService {
        return DB::transaction(function () use ($mesa, $cashRegister, $openedBy, $source, $terminal) {
            $mesa->loadMissing('group.mesas');

            $existing = $this->findActiveForMesa($mesa, $cashRegister->id);
            if ($existing) {
                $this->linkLegacyRecords($existing, $cashRegister->id);

                return $existing;
            }

            $members = $mesa->group?->mesas?->sortBy('number')->values() ?? collect([$mesa]);
            $groupName = $mesa->group?->name;
            $label = $groupName ?: $mesa->display_name;
            $openerName = $source === 'kiosk'
                ? 'Kiosco - '.($terminal?->name ?: 'Terminal')
                : User::find($openedBy)?->name;

            $service = MesaService::create([
                'cash_register_id' => $cashRegister->id,
                'primary_mesa_id' => $mesa->id,
                'mesa_group_id' => $mesa->mesa_group_id,
                'opened_by' => $openedBy,
                'kiosk_terminal_id' => $terminal?->id,
                'source' => $source,
                'status' => 'abierta',
                'service_label' => $label,
                'opener_name_snapshot' => $openerName,
                'group_name_snapshot' => $groupName,
                'opened_at' => now(),
            ]);

            $service->mesas()->attach($members->mapWithKeys(fn (Mesa $member) => [
                $member->id => [
                    'mesa_label_snapshot' => $member->display_name,
                    'is_primary' => $member->id === $mesa->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ])->all());

            $service->load('mesas');
            $this->linkLegacyRecords($service, $cashRegister->id);

            return $service;
        });
    }

    private function linkLegacyRecords(MesaService $service, int $cashRegisterId): void
    {
        $memberIds = $service->mesas()->pluck('mesas.id')->all();

        \App\Models\Order::query()
            ->where('cash_register_id', $cashRegisterId)
            ->whereIn('mesa_id', $memberIds)
            ->whereNull('mesa_service_id')
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
            ->update(['mesa_service_id' => $service->id]);

        \App\Models\MesaAssignment::query()
            ->whereIn('mesa_id', $memberIds)
            ->whereNull('mesa_service_id')
            ->whereNull('released_at')
            ->update(['mesa_service_id' => $service->id]);

        \App\Models\MesaSplit::query()
            ->whereIn('mesa_id', $memberIds)
            ->whereNull('mesa_service_id')
            ->whereIn('status', ['pendiente', 'parcial'])
            ->update(['mesa_service_id' => $service->id]);
    }

    public function markInAccount(Mesa $mesa, int $cashRegisterId): ?MesaService
    {
        $service = $this->findActiveForMesa($mesa, $cashRegisterId);
        $service?->update([
            'status' => 'en_cuenta',
            'in_account_at' => $service->in_account_at ?? now(),
        ]);

        return $service;
    }

    public function reopen(Mesa $mesa, int $cashRegisterId): ?MesaService
    {
        $service = $this->findActiveForMesa($mesa, $cashRegisterId);
        $service?->update(['status' => 'abierta', 'in_account_at' => null]);

        return $service;
    }

    public function completePaid(MesaService $service, int $closedBy): void
    {
        $service->update([
            'status' => 'pagada',
            'closed_by' => $closedBy,
            'closed_at' => now(),
            'total_snapshot' => round((float) $service->orders()->sum('total'), 2),
            'close_reason' => 'Cobrado desde POS',
        ]);
    }

    public function releaseWithoutPayment(MesaService $service, int $closedBy, string $reason): void
    {
        $service->update([
            'status' => 'liberada',
            'closed_by' => $closedBy,
            'closed_at' => now(),
            'total_snapshot' => round((float) $service->orders()->sum('total'), 2),
            'close_reason' => $reason,
        ]);
    }
}
