<?php

namespace App\Support\Pos;

use App\Models\Discount;

/**
 * El carrito del punto de venta como objeto de valor.
 *
 * El carrito vive como un `array` dentro del componente Livewire y se manipula
 * desde muchos sitios. Eso hace que probar una suma exija levantar Livewire,
 * una sesión y una base de datos. Aquí los totales se derivan de una estructura
 * plana, así que se pueden probar con un `array` literal y nada más.
 *
 * Es inmutable a propósito: cada operación devuelve un carrito nuevo. Quien lo
 * usa sigue siendo dueño del estado; esto solo sabe leerlo y sumarlo.
 */
final class CartState
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function __construct(private readonly array $lines) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public static function fromArray(array $lines): self
    {
        return new self(array_values($lines));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * Suma de los subtotales de cada línea, con el descuento ya aplicado.
     */
    public function total(): float
    {
        return round($this->sum('subtotal'), 2);
    }

    /**
     * Unidades en el carrito, no líneas: dos veces el mismo producto son dos.
     */
    public function count(): int
    {
        return (int) $this->sum('quantity');
    }

    public function discountTotal(): float
    {
        return round($this->sum('discount_amount'), 2);
    }

    /**
     * Parte del descuento que corresponde a beneficios de empleado.
     *
     * Se separa porque el corte de caja la reporta aparte del resto.
     */
    public function employeeDiscountTotal(): float
    {
        $audiences = [Discount::AUDIENCE_EMPLOYEES, Discount::AUDIENCE_SELECTED_EMPLOYEES];

        return round(
            $this->sum(
                'discount_amount',
                fn (array $line): bool => in_array(
                    data_get($line, 'discount_snapshot.audience'),
                    $audiences,
                    true
                )
            ),
            2
        );
    }

    /**
     * Unidades por producto, para el distintivo "en el pedido" del catálogo.
     *
     * Las líneas sin `product_id` (promociones armadas) no cuentan: no apuntan
     * a una tarjeta del catálogo.
     *
     * @return array<int, int>
     */
    public function quantitiesByProduct(): array
    {
        $quantities = [];

        foreach ($this->lines as $line) {
            $productId = $line['product_id'] ?? null;

            if (empty($productId)) {
                continue;
            }

            $quantities[(int) $productId] = ($quantities[(int) $productId] ?? 0)
                + (int) ($line['quantity'] ?? 0);
        }

        return $quantities;
    }

    /**
     * Índice de la línea que coincide en producto, complementos e ingredientes.
     *
     * Es lo que decide si tocar un producto agrega una línea nueva o sube la
     * cantidad de una existente. Replica exactamente el criterio que tenía
     * `PointOfSale::findDuplicateCartItem()`.
     *
     * @param  array<int, array<string, mixed>>  $addons
     * @param  array<int, array<string, mixed>>  $ingredients
     */
    public function findMatchingLine(int $productId, array $addons, array $ingredients): ?int
    {
        $wantedAddons = $this->addonSignature($addons);
        $wantedIngredients = $this->ingredientSignature($ingredients);

        foreach ($this->lines as $index => $line) {
            if (($line['product_id'] ?? null) !== $productId) {
                continue;
            }

            if ($this->addonSignature($line['addons'] ?? []) !== $wantedAddons) {
                continue;
            }

            if ($this->ingredientSignature($line['ingredients'] ?? []) !== $wantedIngredients) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function sum(string $field, ?callable $filter = null): float
    {
        $total = 0.0;

        foreach ($this->lines as $line) {
            if ($filter !== null && ! $filter($line)) {
                continue;
            }

            $total += (float) ($line[$field] ?? 0);
        }

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $addons
     * @return array<int, mixed>
     */
    private function addonSignature(array $addons): array
    {
        return collect($addons)->pluck('addon_id')->sort()->values()->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $ingredients
     * @return array<int, array<int, mixed>>
     */
    private function ingredientSignature(array $ingredients): array
    {
        return collect($ingredients)
            ->sortBy('ingredient_id')
            ->map(fn ($ingredient) => [$ingredient['ingredient_id'], $ingredient['quantity']])
            ->values()
            ->toArray();
    }
}
