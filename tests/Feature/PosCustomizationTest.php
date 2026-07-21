<?php

namespace Tests\Feature;

use App\Livewire\Pos\PointOfSale;
use App\Models\Addon;
use App\Models\AddonGroup;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
