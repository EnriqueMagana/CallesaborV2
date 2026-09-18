<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Única puerta para abrir (o reabrir) una caja.
 *
 * El POS trabaja siempre sobre una sola caja activa: si hubiera dos, tomaría
 * la más reciente y las ventas de la otra quedarían fuera de su vista y de
 * su corte. La interfaz oculta el formulario cuando ya hay una abierta, pero
 * dos pestañas o un doble clic lo saltan; por eso la regla vive aquí.
 */
class CashRegisterOpenService
{
    /** Candado compartido por abrir y reabrir: nunca corren a la vez. */
    public const LOCK = 'cash-register:open';

    public function open(User $actor, string $name, float $initialAmount): CashRegister
    {
        return $this->exclusively(function () use ($actor, $name, $initialAmount): CashRegister {
            $this->assertNoneOpen();

            return CashRegister::create([
                'name' => trim($name),
                'opened_by' => $actor->id,
                'initial_amount' => $initialAmount,
                'opened_at' => now(),
                'is_open' => true,
            ]);
        });
    }

    public function assertNoneOpen(): void
    {
        $open = CashRegister::query()->where('is_open', true)->lockForUpdate()->first(['id', 'name']);

        if ($open) {
            throw ValidationException::withMessages([
                'register' => "Ya hay una caja abierta («{$open->name}»). Ciérrala antes de abrir otra.",
            ]);
        }
    }

    /**
     * Ejecuta el callback con el candado tomado y dentro de una transacción.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function exclusively(callable $callback): mixed
    {
        try {
            return Cache::lock(self::LOCK, 10)->block(5, fn () => DB::transaction($callback));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'register' => 'Otra persona está abriendo una caja en este momento. Intenta de nuevo en unos segundos.',
            ]);
        }
    }
}
