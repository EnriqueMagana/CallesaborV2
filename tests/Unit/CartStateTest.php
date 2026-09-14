<?php

namespace Tests\Unit;

use App\Models\Discount;
use App\Support\Pos\CartState;
use PHPUnit\Framework\TestCase;

/**
 * El objetivo de `CartState` es este archivo: sumar el carrito sin levantar
 * Livewire, ni sesión, ni base de datos. Si alguna de estas pruebas empieza a
 * necesitar `RefreshDatabase`, es señal de que se le está metiendo al objeto de
 * valor lógica que no le toca.
 */
class CartStateTest extends TestCase
{
    public function test_an_empty_cart_totals_zero(): void
    {
        $cart = CartState::empty();

        $this->assertTrue($cart->isEmpty());
        $this->assertSame(0.0, $cart->total());
        $this->assertSame(0, $cart->count());
        $this->assertSame(0.0, $cart->discountTotal());
        $this->assertSame([], $cart->quantitiesByProduct());
    }

    public function test_it_sums_subtotals_and_counts_units_not_lines(): void
    {
        $cart = CartState::fromArray([
            ['product_id' => 1, 'quantity' => 3, 'subtotal' => 150.0],
            ['product_id' => 2, 'quantity' => 1, 'subtotal' => 49.5],
        ]);

        $this->assertSame(199.5, $cart->total());
        $this->assertSame(4, $cart->count());
    }

    public function test_totals_are_rounded_to_cents(): void
    {
        $cart = CartState::fromArray([
            ['product_id' => 1, 'quantity' => 1, 'subtotal' => 10.005],
            ['product_id' => 2, 'quantity' => 1, 'subtotal' => 0.004],
        ]);

        $this->assertSame(10.01, $cart->total());
    }

    public function test_it_separates_the_employee_share_of_the_discount(): void
    {
        $cart = CartState::fromArray([
            [
                'product_id' => 1, 'quantity' => 1, 'subtotal' => 90.0,
                'discount_amount' => 10.0,
                'discount_snapshot' => ['audience' => Discount::AUDIENCE_EMPLOYEES],
            ],
            [
                'product_id' => 2, 'quantity' => 1, 'subtotal' => 75.0,
                'discount_amount' => 25.0,
                'discount_snapshot' => ['audience' => 'everyone'],
            ],
        ]);

        $this->assertSame(35.0, $cart->discountTotal());
        $this->assertSame(10.0, $cart->employeeDiscountTotal());
    }

    public function test_quantities_by_product_add_up_across_lines_and_skip_promotions(): void
    {
        $cart = CartState::fromArray([
            ['product_id' => 7, 'quantity' => 2, 'subtotal' => 40.0],
            ['product_id' => 7, 'quantity' => 1, 'subtotal' => 25.0],
            ['product_id' => null, 'quantity' => 5, 'subtotal' => 99.0],
        ]);

        $this->assertSame([7 => 3], $cart->quantitiesByProduct());
    }

    public function test_a_plain_product_matches_an_existing_identical_line(): void
    {
        $cart = CartState::fromArray([
            ['product_id' => 4, 'quantity' => 1, 'subtotal' => 30.0, 'addons' => [], 'ingredients' => []],
        ]);

        $this->assertSame(0, $cart->findMatchingLine(4, [], []));
        $this->assertNull($cart->findMatchingLine(5, [], []));
    }

    public function test_the_same_addons_in_another_order_are_still_the_same_line(): void
    {
        $cart = CartState::fromArray([
            [
                'product_id' => 4, 'quantity' => 1, 'subtotal' => 30.0,
                'addons' => [['addon_id' => 9], ['addon_id' => 3]],
                'ingredients' => [],
            ],
        ]);

        $this->assertSame(0, $cart->findMatchingLine(4, [['addon_id' => 3], ['addon_id' => 9]], []));
    }

    public function test_a_different_addon_selection_is_a_different_line(): void
    {
        $cart = CartState::fromArray([
            [
                'product_id' => 4, 'quantity' => 1, 'subtotal' => 30.0,
                'addons' => [['addon_id' => 9]],
                'ingredients' => [],
            ],
        ]);

        $this->assertNull($cart->findMatchingLine(4, [['addon_id' => 3]], []));
        $this->assertNull($cart->findMatchingLine(4, [], []));
    }

    public function test_ingredient_quantities_are_part_of_the_line_identity(): void
    {
        $cart = CartState::fromArray([
            [
                'product_id' => 4, 'quantity' => 1, 'subtotal' => 30.0,
                'addons' => [],
                'ingredients' => [['ingredient_id' => 2, 'quantity' => 1]],
            ],
        ]);

        $this->assertSame(0, $cart->findMatchingLine(4, [], [['ingredient_id' => 2, 'quantity' => 1]]));
        $this->assertNull($cart->findMatchingLine(4, [], [['ingredient_id' => 2, 'quantity' => 2]]));
    }

    public function test_it_hands_back_the_lines_it_was_given(): void
    {
        $lines = [['product_id' => 1, 'quantity' => 1, 'subtotal' => 10.0]];

        $this->assertSame($lines, CartState::fromArray($lines)->toArray());
    }
}
