<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ProductCustomizationService;

/**
 * Borrador de productos de una solicitud de cambio.
 *
 * El editor de productos (mini POS) lo arma y el wizard lo recibe para pedir
 * el motivo y enviarlo. Vive en la sesión y guarda una huella de la orden: si
 * la orden cambió mientras tanto, el borrador se descarta en lugar de aplicar
 * cantidades sobre artículos que ya no son los mismos.
 *
 * Formato de línea (el mismo que acepta OrderChangeRequestService::create):
 *   existing: key, kind, order_item_id, product_id, name, quantity, original_quantity, unit_subtotal, modifiers
 *   new:      key, kind, product_id, name, quantity, original_quantity=0, unit_subtotal, addons[], ingredients{id:qty}, notes, modifiers
 */
class OrderChangeDraft
{
    public static function key(Order $order): string
    {
        return 'order-change-draft.'.$order->id;
    }

    /**
     * Líneas actuales de la orden. Requiere items no cancelados con addons e ingredientes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function linesFromOrder(Order $order): array
    {
        $customization = app(ProductCustomizationService::class);

        return $order->items
            ->reject(fn (OrderItem $item) => (bool) $item->is_cancelled)
            ->map(fn (OrderItem $item) => [
                'key' => 'existing-'.$item->id,
                'kind' => 'existing',
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'original_quantity' => (int) $item->quantity,
                'unit_subtotal' => round((float) $item->subtotal / max(1, (int) $item->quantity), 2),
                'modifiers' => $customization->summary(
                    $item->addons->map(fn ($addon) => ['addon_name' => $addon->addon_name])->all(),
                    $item->ingredients->map(fn ($ingredient) => ['ingredient_name' => $ingredient->ingredient_name, 'quantity' => (int) $ingredient->quantity])->all(),
                    $item->notes,
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * Agrega una línea nueva o suma cantidad si ya hay una idéntica (mismo
     * producto, extras, ingredientes y nota).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array  $built  resultado de ProductCustomizationService::build()
     * @return array<int, array<string, mixed>>
     */
    public static function addNewLine(array $lines, int $productId, string $name, array $built, ?int $replaceIndex = null): array
    {
        if ($replaceIndex !== null && ($lines[$replaceIndex]['kind'] ?? null) === 'new') {
            array_splice($lines, $replaceIndex, 1);
        }

        $addonIds = collect($built['addons'])->pluck('addon_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $ingredients = collect($built['ingredients'])->mapWithKeys(fn (array $item) => [(int) $item['ingredient_id'] => (int) $item['quantity']])->sortKeys()->all();
        $key = 'new-'.$productId.'-'.substr(md5(json_encode([$addonIds, $ingredients, $built['notes']])), 0, 10);

        foreach ($lines as $index => $line) {
            if (($line['key'] ?? null) === $key) {
                $lines[$index]['quantity'] = min(ProductCustomizationService::MAX_QUANTITY, (int) $line['quantity'] + (int) $built['quantity']);

                return $lines;
            }
        }

        $lines[] = [
            'key' => $key,
            'kind' => 'new',
            'product_id' => $productId,
            'name' => $name,
            'quantity' => (int) $built['quantity'],
            'original_quantity' => 0,
            'unit_subtotal' => (float) $built['unit_total'],
            'addons' => $addonIds,
            'ingredients' => $ingredients,
            'notes' => $built['notes'],
            'modifiers' => $built['summary'],
        ];

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public static function total(array $lines): float
    {
        return round(collect($lines)->sum(fn (array $line) => (float) $line['unit_subtotal'] * max(0, (int) $line['quantity'])), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{removed: int, added: int, updated: int}
     */
    public static function summary(array $lines): array
    {
        return collect($lines)->reduce(function (array $summary, array $line): array {
            $before = (int) ($line['original_quantity'] ?? 0);
            $after = (int) ($line['quantity'] ?? 0);
            if (($line['kind'] ?? null) === 'new') {
                $summary['added'] += $after;
            } elseif ($after === 0) {
                $summary['removed'] += $before;
            } elseif ($after !== $before) {
                $summary['updated']++;
            }

            return $summary;
        }, ['removed' => 0, 'added' => 0, 'updated' => 0]);
    }

    public static function hasChanges(array $lines): bool
    {
        $summary = self::summary($lines);

        return $summary['removed'] + $summary['added'] + $summary['updated'] > 0;
    }

    /**
     * Huella de los artículos vigentes: si cambia, el borrador ya no aplica.
     */
    public static function fingerprint(Order $order): string
    {
        return md5(json_encode($order->items
            ->reject(fn (OrderItem $item) => (bool) $item->is_cancelled)
            ->map(fn (OrderItem $item) => [(int) $item->id, (int) $item->quantity, (string) $item->subtotal])
            ->sortBy(0)
            ->values()
            ->all()));
    }

    public static function save(Order $order, array $lines): void
    {
        session()->put(self::key($order), [
            'fingerprint' => self::fingerprint($order),
            'lines' => array_values($lines),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public static function load(Order $order): ?array
    {
        $draft = session()->get(self::key($order));
        if (! is_array($draft) || ($draft['fingerprint'] ?? null) !== self::fingerprint($order)) {
            self::forget($order);

            return null;
        }

        return $draft['lines'] ?? null;
    }

    public static function forget(Order $order): void
    {
        session()->forget(self::key($order));
    }
}
