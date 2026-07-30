<?php

namespace Tests\Feature;

use App\Livewire\Pos\PointOfSale;
use App\Models\Addon;
use App\Models\AddonGroup;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PosCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_group_with_one_option_is_preselected_even_when_minimum_is_zero(): void
    {
        [$product, $group, $addon] = $this->customizableProduct(optionCount: 1);

        Livewire::test(PointOfSale::class)
            ->call('openCustomizeModal', $product->id)
            ->assertSet("selectedAddons.{$addon->id}", true)
            ->assertSet('customizationIsValid', true);
    }

    public function test_single_choice_group_replaces_the_previous_selection(): void
    {
        [$product, $group, $first, $second] = $this->customizableProduct(optionCount: 2);

        Livewire::test(PointOfSale::class)
            ->call('openCustomizeModal', $product->id)
            ->call('toggleAddon', $first->id)
            ->call('toggleAddon', $second->id)
            ->assertSet("selectedAddons.{$first->id}", null)
            ->assertSet("selectedAddons.{$second->id}", true)
            ->assertSet('customizationIsValid', true);
    }

    public function test_required_group_with_zero_configured_minimum_is_still_validated_as_one(): void
    {
        [$product, $group] = $this->customizableProduct(optionCount: 2);

        Livewire::test(PointOfSale::class)
            ->call('openCustomizeModal', $product->id)
            ->set('selectedAddons', [])
            ->call('addToCart')
            ->assertHasErrors("addons_{$group->id}");
    }

    public function test_pasta_then_hamburger_and_reverse_remain_interactive(): void
    {
        $pasta = Product::create([
            'name' => 'Pasta',
            'price' => 120,
            'is_customizable' => true,
            'min_ingredients' => 1,
            'max_ingredients' => 2,
            'is_active' => true,
        ]);
        $ingredient = Ingredient::create(['name' => 'Salsa', 'is_active' => true]);
        $pasta->ingredients()->attach($ingredient->id);
        $hamburger = Product::create([
            'name' => 'Hamburguesa',
            'price' => 95,
            'is_active' => true,
        ]);

        Livewire::test(PointOfSale::class)
            ->call('openCustomizeModal', $pasta->id)
            ->call('confirmCustomize', [], [$ingredient->id => 1], 1, '')
            ->assertSet('showCustomizeModal', false)
            ->assertSet('customizingProductId', null)
            ->assertSet('selectedIngredients', [])
            ->call('openCustomizeModal', $hamburger->id)
            ->assertCount('cart', 2)
            ->assertSet('cart.0.product_name', 'Pasta')
            ->assertSet('cart.1.product_name', 'Hamburguesa');

        Livewire::test(PointOfSale::class)
            ->set('cart', [])
            ->call('openCustomizeModal', $hamburger->id)
            ->call('openCustomizeModal', $pasta->id)
            ->call('confirmCustomize', [], [$ingredient->id => 1], 1, '')
            ->assertCount('cart', 2)
            ->assertSet('cart.0.product_name', 'Hamburguesa')
            ->assertSet('cart.1.product_name', 'Pasta');
    }

    public function test_server_revalidates_product_limits_from_single_confirmation(): void
    {
        [$product, $group, $first, $second] = $this->customizableProduct(optionCount: 2);
        $product->update(['max_addons' => 1, 'min_ingredients' => 1, 'max_ingredients' => 1]);
        $ingredient = Ingredient::create(['name' => 'Queso', 'is_active' => true]);
        $product->ingredients()->attach($ingredient->id);

        $component = Livewire::test(PointOfSale::class)
            ->call('openCustomizeModal', $product->id)
            ->call('confirmCustomize', [$first->id, $second->id], [$ingredient->id => 1], 1, '')
            ->assertHasErrors('addons_'.$group->id);

        $component
            ->call('confirmCustomize', [$first->id], [$ingredient->id => 2], 1, '')
            ->assertHasErrors('ingredients')
            ->call('confirmCustomize', [$first->id], [$ingredient->id => 1], 1, 'Sin picante')
            ->assertHasNoErrors()
            ->assertCount('cart', 1)
            ->assertSet('cart.0.notes', 'Sin picante');
    }

    public function test_customizer_uses_local_controls_and_renders_all_available_images(): void
    {
        $user = User::factory()->create();
        CashRegister::create([
            'name' => 'Caja prueba',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        [$product, $group, $addon] = $this->customizableProduct(optionCount: 1);
        $product->update(['image' => 'menu/pasta.webp']);
        $addon->update(['image' => 'addons/salsa.webp']);
        $ingredient = Ingredient::create([
            'name' => 'Albahaca',
            'image' => 'ingredients/albahaca.webp',
            'is_active' => true,
        ]);
        $product->ingredients()->attach($ingredient->id);

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openCustomizeModal', $product->id)
            ->assertSee('menu/pasta.webp', false)
            ->assertSee('addons/salsa.webp', false)
            ->assertSee('ingredients/albahaca.webp', false)
            ->assertSee('x-model.debounce.120ms="ingredientQuery"', false)
            ->assertSee('ingredientMatches(', false)
            ->assertSee('changeIngredient(', false)
            ->assertSee('pos-ingredient-counter', false)
            ->assertSee('pos-ingredient-card__quantity', false)
            ->assertSee('Seleccionado ·', false)
            ->assertSee('submit($wire)', false)
            ->assertDontSee('wire:click="incrementIngredient', false)
            ->assertDontSee('wire:click="toggleAddon', false);
    }

    public function test_catalog_does_not_issue_one_customization_query_per_product(): void
    {
        $user = User::factory()->create();
        CashRegister::create([
            'name' => 'Caja catálogo',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $category = Category::create(['name' => 'Catálogo', 'is_active' => true]);

        foreach (range(1, 12) as $number) {
            Product::create([
                'category_id' => $category->id,
                'name' => "Producto {$number}",
                'price' => $number,
                'is_active' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->assertSee('Producto 12');

        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn ($query) => mb_strtolower($query));

        $this->assertLessThanOrEqual(
            2,
            $queries->filter(fn ($query) => str_contains($query, 'addon_groups'))->count(),
        );
        $this->assertLessThanOrEqual(
            2,
            $queries->filter(fn ($query) => str_contains($query, 'product_ingredient'))->count(),
        );
    }

    private function customizableProduct(int $optionCount): array
    {
        $product = Product::create([
            'name' => 'Pasta grande',
            'price' => 165,
            'is_customizable' => true,
            'is_active' => true,
        ]);

        $group = AddonGroup::create([
            'name' => 'Salsa',
            'is_required' => true,
            'min_selections' => 0,
            'max_selections' => 1,
            'is_active' => true,
        ]);

        $product->addonGroups()->attach($group->id);

        $addons = collect(range(1, $optionCount))->map(fn (int $index) => Addon::create([
            'addon_group_id' => $group->id,
            'name' => "Opción {$index}",
            'is_active' => true,
        ]))->all();

        return [$product, $group, ...$addons];
    }
}
