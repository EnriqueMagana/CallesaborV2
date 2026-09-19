<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Reparte pagos entre órdenes en centavos exactos.
 *
 * Al cobrar una mesa con varias comandas, cada pago (efectivo, tarjeta…) se
 * registra por orden. Repartir cada pago proporcionalmente y redondear por
 * separado descuadra centavos: 33.33/33.33/33.34 pagados con $50 + $50 dejaban
 * $50.01 en efectivo. Aquí se llenan las órdenes en orden con los pagos en
 * orden, así se cumplen las dos sumas a la vez:
 *   - lo registrado de cada pago suma exactamente lo cobrado con él;
 *   - cada orden queda cubierta exactamente por su total.
 */
class PaymentAllocator
{
    /**
     * @param  array<int|string, int>  $targets  clave de orden => centavos que debe recibir
     * @param  array<int, int>  $payments  índice de pago => centavos cobrados
     * @return array<int, array{target: int|string, payment: int, cents: int}>
     */
    public static function allocate(array $targets, array $payments): array
    {
        $targets = array_filter($targets, fn (int $cents) => $cents > 0);
        $payments = array_filter($payments, fn (int $cents) => $cents > 0);

        if (array_sum($targets) !== array_sum($payments)) {
            throw new InvalidArgumentException(sprintf(
                'Lo cobrado (%d centavos) no coincide con lo que se debe repartir (%d centavos).',
                array_sum($payments),
                array_sum($targets),
            ));
        }

        $allocations = [];
        $targetKeys = array_keys($targets);
        $paymentKeys = array_keys($payments);
        $t = 0;
        $p = 0;
        $targetLeft = $targets[$targetKeys[0] ?? 0] ?? 0;
        $paymentLeft = $payments[$paymentKeys[0] ?? 0] ?? 0;

        while ($t < count($targetKeys) && $p < count($paymentKeys)) {
            $cents = min($targetLeft, $paymentLeft);
            if ($cents > 0) {
                $allocations[] = ['target' => $targetKeys[$t], 'payment' => $paymentKeys[$p], 'cents' => $cents];
            }

            $targetLeft -= $cents;
            $paymentLeft -= $cents;

            if ($targetLeft === 0 && ++$t < count($targetKeys)) {
                $targetLeft = $targets[$targetKeys[$t]];
            }
            if ($paymentLeft === 0 && ++$p < count($paymentKeys)) {
                $paymentLeft = $payments[$paymentKeys[$p]];
            }
        }

        return $allocations;
    }

    /**
     * Ajusta montos proporcionales para que sumen exactamente `$total`,
     * asignando la diferencia de redondeo al más grande.
     *
     * @param  array<int|string, float>  $amounts
     * @return array<int|string, int> centavos
     */
    public static function toCentsSummingTo(array $amounts, int $totalCents): array
    {
        $cents = array_map(fn (float $amount) => (int) round($amount * 100), $amounts);
        if ($cents === []) {
            return [];
        }

        $difference = $totalCents - array_sum($cents);
        if ($difference !== 0) {
            $largest = array_keys($cents, max($cents))[0];
            $cents[$largest] += $difference;
        }

        return $cents;
    }
}
