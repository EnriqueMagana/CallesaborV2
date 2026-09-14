<?php

namespace Tests\Feature;

use App\Livewire\Pos\Catalog;
use App\Livewire\Pos\PointOfSale;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El catálogo es un componente hijo para que el carrito deje de reconstruirlo
 * en cada click. Estas pruebas cuidan las dos condiciones de las que depende
 * ese aislamiento: que no dibuje nada derivado del carrito, y que los clicks
 * sigan llegando al padre en un solo viaje.
 */
class PosCatalogComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_does_not_render_anything_derived_from_the_cart(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/pos/catalog.blade.php'));

        // Si el catálogo vuelve a leer `$cart` en Blade, el padre tendrá que
        // re-renderizarlo en cada click y el aislamiento se pierde en silencio.
        $this->assertStringNotContainsString('$cart', $blade);
        $this->assertStringContainsString('cartQtyFor(', $blade);
    }

    public function test_product_clicks_reach_the_parent_in_a_single_round_trip(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/pos/catalog.blade.php'));

        $this->assertStringContainsString('wire:click="$parent.openCustomizeModal(', $blade);
        $this->assertStringContainsString('wire:click="$parent.selectPromotionFromCatalog(', $blade);
    }

    public function test_the_catalog_lists_active_products_and_hides_inactive_ones(): void
    {
        [$user, $product] = $this->catalogScenario();
        Product::create([
            'category_id' => $product->category_id,
            'name' => 'Producto retirado',
            'price' => 10,
            'is_active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(Catalog::class)
            ->assertSee($product->name)
            ->assertDontSee('Producto retirado');
    }

    public function test_changing_the_order_type_returns_the_catalog_to_the_products_tab(): void
    {
        [$user] = $this->catalogScenario();

        Livewire::actingAs($user)
            ->test(Catalog::class)
            ->set('catalogMode', 'promotions')
            ->call('resetToProducts')
            ->assertSet('catalogMode', 'products');
    }

    public function test_the_parent_tells_the_catalog_when_the_order_type_changes(): void
    {
        [$user] = $this->catalogScenario();

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->set('orderType', 'delivery')
            ->assertDispatched('pos-order-type-changed');
    }

    public function test_saving_a_promotion_clears_the_cached_catalog_list(): void
    {
        [$user] = $this->catalogScenario();

        Livewire::actingAs($user)->test(Catalog::class, ['orderType' => 'delivery']);

        $key = Promotion::posCacheKey('delivery');
        $this->assertTrue(Cache::has($key), 'El catálogo no cacheó las promociones.');

        Promotion::create([
            'name' => 'Promo nueva',
            'price' => 50,
            'starts_on' => now()->subDay(),
            'fulfillment_modes' => ['takeaway', 'delivery'],
            'is_active' => true,
            'show_on_pos' => true,
        ]);

        $this->assertFalse(
            Cache::has($key),
            'Guardar una promoción debe invalidar la caché que lee el catálogo.'
        );
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function catalogScenario(): array
    {
        $user = User::factory()->create();

        CashRegister::create([
            'name' => 'Caja catálogo',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $category = Category::create(['name' => 'Principales', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Producto catálogo',
            'price' => 75,
            'is_customizable' => false,
            'is_active' => true,
        ]);

        return [$user, $product];
    }
}
