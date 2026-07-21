<?php

namespace Tests\Feature;

use App\Livewire\Menu\MenuBuilder;
use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MenuIngredientConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingredient_catalog_keeps_compact_cards_and_renders_the_image(): void
    {
        Ingredient::create([
            'name' => 'Brócoli',
            'description' => 'Porción fresca',
            'image' => 'ingredients/broccoli.webp',
            'extra_price' => 12.50,
            'sort_order' => 4,
            'is_active' => true,
        ]);

        Livewire::test(MenuBuilder::class)
            ->set('tab', 'ingredients')
            ->assertSee('Brócoli')
            ->assertSee('storage/ingredients/broccoli.webp', false)
            ->assertSee('Imagen de Brócoli')
            ->assertSee('menu-media-52', false)
            ->assertDontSee('menu-ingredient-card__media', false);
    }

    public function test_ingredient_modal_renders_the_existing_image_preview(): void
    {
        $ingredient = Ingredient::create([
            'name' => 'Brócoli',
            'image' => 'ingredients/broccoli.webp',
            'is_active' => true,
        ]);

        Livewire::test(MenuBuilder::class)
            ->call('openIngredientModal', $ingredient->id)
            ->assertSee('Vista previa')
            ->assertSee('storage/ingredients/broccoli.webp', false)
            ->assertSee('Imagen actual de Brócoli')
            ->assertSee('Quitar la imagen actual al guardar');
    }

    public function test_existing_ingredient_image_can_be_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ingredients/old.webp', 'image');

        $ingredient = Ingredient::create([
            'name' => 'Zanahoria',
            'image' => 'ingredients/old.webp',
            'is_active' => true,
        ]);

        Livewire::test(MenuBuilder::class)
            ->call('openIngredientModal', $ingredient->id)
            ->set('ingRemoveImage', true)
            ->call('saveIngredient')
            ->assertHasNoErrors();

        $this->assertNull($ingredient->fresh()->image);
        Storage::disk('public')->assertMissing('ingredients/old.webp');
    }
}
