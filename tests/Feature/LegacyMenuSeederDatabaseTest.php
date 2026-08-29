<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\LegacyMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class LegacyMenuSeederDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_the_menu_and_is_idempotent(): void
    {
        Storage::fake('public');
        $prepared = (new LegacyMenuSeeder)->prepare(database_path('seeders/data/legacy-menu.json'));

        foreach ($prepared['products'] as $product) {
            Storage::disk('public')->put($product['image'], 'image-'.$product['sort_order']);
        }

        $category = Category::query()->create([
            'name' => 'Categoría existente',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $currentProduct = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Producto actual que debe retirarse',
            'price' => 10,
            'is_active' => true,
        ]);
        $seeder = new LegacyMenuSeeder;

        $seeder->run();
        $seeder->run();

        $this->assertSame(1, Category::query()->count());
        $this->assertSame(88, Product::query()->count());
        $this->assertSame(89, Product::withTrashed()->count());
        $this->assertSame(88, Product::query()->distinct()->count('name'));
        $this->assertTrue($currentProduct->fresh()->trashed());

        $product = Product::query()->where('name', 'Pasta Grande')->firstOrFail();
        $this->assertNull($product->category_id);
        $this->assertSame('165.00', $product->price);
        $this->assertSame('menus/1759812806-pasta grande 2.webp', $product->image);
        $this->assertTrue($product->is_customizable);
        $this->assertSame(6, $product->max_addons);

        $this->assertSame(0, Product::query()->whereNotNull('category_id')->count());
        Storage::disk('public')->assertExists('menus/1759812806-pasta grande 2.webp');
        Storage::disk('public')->assertMissing('products/1759812806-pasta grande 2.webp');

        $this->assertDatabaseMissing('products', ['name' => 'Pasta de camarones grande']);
    }

    public function test_missing_images_abort_before_the_existing_catalog_is_removed(): void
    {
        Storage::fake('public');
        $currentProduct = Product::query()->create([
            'name' => 'Producto que debe conservarse si falta una imagen',
            'price' => 10,
            'is_active' => true,
        ]);

        try {
            (new LegacyMenuSeeder)->run();
            $this->fail('El seeder debio detenerse por imagenes faltantes.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('storage/app/public/menus', $exception->getMessage());
        }

        $this->assertFalse($currentProduct->fresh()->trashed());
        $this->assertSame(1, Product::query()->count());
    }
}
