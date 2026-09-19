<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashRegisterCut;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Reapertura de una caja cerrada por error.
 *
 * El corte no se borra: queda anulado con quién, cuándo y por qué. Sólo se
 * reabre la caja cerrada más reciente y sólo si no hay otra abierta, porque
 * el POS trabaja siempre sobre una única caja activa y reabrir una caja
 * antigua alteraría cortes posteriores ya conciliados.
 */
class CashRegisterReopenService
{
    public function canReopen(User $actor): bool
    {
        return $actor->hasRole('super-admin');
    }

    /**
     * Motivo por el que este corte no puede reabrirse, o null si sí puede.
     */
    public function blockReason(CashRegisterCut $cut): ?string
    {
        if ($cut->is_reopened) {
            return 'Este corte ya fue anulado por una reapertura.';
        }

        $register = $cut->cashRegister;
        if (! $register || $register->is_open) {
            return 'La caja de este corte ya está abierta.';
        }

        if (CashRegister::query()->where('is_open', true)->exists()) {
            return 'Hay otra caja abierta. Ciérrala antes de reabrir esta.';
        }

        $latestClosed = CashRegister::query()
            ->where('is_open', false)
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->latest('id')
            ->value('id');
        if ((int) $latestClosed !== (int) $register->id) {
            return 'Sólo puede reabrirse la última caja cerrada.';
        }

        $currentCut = $register->cuts()->whereNull('reopened_at')->latest('generated_at')->latest('id')->value('id');
        if ((int) $currentCut !== (int) $cut->id) {
            return 'Sólo puede anularse el corte vigente de la caja.';
        }

        return null;
    }

    public function reopen(CashRegisterCut $cut, User $actor, string $reason): CashRegister
    {
        if (! $this->canReopen($actor)) {
            throw new AuthorizationException('Sólo un super-admin puede reabrir una caja.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['reopenReason' => 'Explica el motivo con al menos 10 caracteres.']);
        }

        // Mismo candado que abrir caja: una apertura y una reapertura
        // simultáneas no pueden dejar dos cajas abiertas.
        return app(CashRegisterOpenService::class)->exclusively(function () use ($cut, $actor, $reason): CashRegister {
            CashRegister::query()->lockForUpdate()->get(['id']);
            $cut = CashRegisterCut::query()->with('cashRegister')->lockForUpdate()->findOrFail($cut->id);

            if ($blocked = $this->blockReason($cut)) {
                throw ValidationException::withMessages(['reopenReason' => $blocked]);
            }

            $register = $cut->cashRegister;

            // El cierre dio por entregado el efectivo contra entrega; al reabrir
            // vuelve a ser provisión mientras la orden siga activa.
            $settledIds = collect(data_get($cut->cut_data, 'settled_provisional_payment_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter();
            if ($settledIds->isNotEmpty()) {
                OrderPayment::query()
                    ->whereIn('id', $settledIds)
                    ->whereIn('order_id', Order::query()
                        ->where('cash_register_id', $register->id)
                        ->where('status', '!=', 'cancelada')
                        ->select('id'))
                    ->update(['is_provisional' => true]);
            }

            $cut->update([
                'reopened_at' => now(),
                'reopened_by' => $actor->id,
                'reopen_reason' => $reason,
            ]);

            $register->update([
                'is_open' => true,
                'closed_by' => null,
                'closed_at' => null,
                'final_amount' => null,
                'declared_amount' => null,
                'difference_amount' => null,
                'closing_notes' => null,
            ]);

            return $register->refresh();
        });
    }

    /**
     * Folio del siguiente corte de la caja. El primero es CORTE-0004; si la
     * caja se reabrió, los siguientes llevan sufijo (CORTE-0004-2) para no
     * repetir el folio del corte anulado.
     */
    public function nextFolio(CashRegister $register): string
    {
        $base = 'CORTE-'.str_pad((string) $register->id, 4, '0', STR_PAD_LEFT);
        $previous = $register->cuts()->count();

        return $previous === 0 ? $base : $base.'-'.($previous + 1);
    }
}
