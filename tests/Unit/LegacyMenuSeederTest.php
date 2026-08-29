<?php

namespace Tests\Unit;

use Database\Seeders\LegacyMenuSeeder;
use PHPUnit\Framework\TestCase;

class LegacyMenuSeederTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = dirname(__DIR__, 2).'/database/seeders/data/legacy-menu.json';
    }

    public function test_prepare_maps_active_products_without_categories_and_with_descriptions_and_images(): void
    {
        $prepared = (new LegacyMenuSeeder)->prepare($this->source);

        $this->assertSame(88, $prepared['source_rows']);
        $this->assertSame(2, $prepared['deleted_skipped']);
        $this->assertCount(88, $prepared['products']);
        $this->assertCount(88, collect($prepared['products'])->pluck('image')->filter()->unique());
        $this->assertSame(14, $prepared['descriptions_enriched']);

        $products = collect($prepared['products'])->keyBy('name');
        $this->assertSame('menus/1759812806-pasta grande 2.webp', $products['Pasta Grande']['image']);
        $this->assertSame('165.00', $products['Pasta Grande']['price']);
        $this->assertTrue($products['Pasta Grande']['is_customizable']);
        $this->assertSame(6, $products['Pasta Grande']['max_addons']);
        $this->assertNull($products['Pasta Grande']['category_id']);
        $this->assertNull($products['Hamburguesa de Res']['max_addons']);
        $this->assertStringContainsString('Porción extra', $products['Salsa Bufalo Extra']['description']);
        $this->assertFalse($products->has('Pasta de camarones grande'));
        $this->assertFalse($products->has('Pasta de camarones chica'));

        foreach ($prepared['products'] as $product) {
            $this->assertNotEmpty($product['description']);
            $this->assertNull($product['category_id']);
        }
    }
}
