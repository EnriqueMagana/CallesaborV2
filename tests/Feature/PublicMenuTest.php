<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\AddonGroup;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DigitalMenuSetting;
use App\Models\Ingredient;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_route_displays_the_configured_public_menu_without_order_actions(): void
    {
        $business = BusinessSetting::current();
        $business->update([
            'business_name' => 'Cocina Verde',
            'banner_path' => 'business/banner-menu.webp',
            'primary_color' => '#166534',
            'instagram_url' => 'https://instagram.com/cocinaverde',
            'featured_product_ids' => [],
        ]);
        DigitalMenuSetting::current()->update([
            'primary_color' => '#166534',
            'banner_paths' => [['path' => 'business/banner-menu.webp', 'alt' => 'Banner de Cocina Verde']],
            'featured_product_ids' => [],
        ]);

        $category = Category::create([
            'name' => 'Especialidades',
            'description' => 'Recetas de la casa',
            'icon' => 'bx-bowl-hot',
            'color' => '#166534',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Taco de la casa',
            'description' => 'Con ingredientes frescos',
            'image' => 'products/taco.webp',
            'price' => 49.50,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Producto oculto',
            'price' => 10,
            'is_active' => false,
        ]);

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee('Cocina Verde')
            ->assertSee('id="menu-search-form"', false)
            ->assertSee('Buscar platillo o ingrediente')
            ->assertSee('data-category-all', false)
            ->assertSee('product-card__action', false)
            ->assertSee('menu-cover__topbar', false)
            ->assertSee('menu-identity', false)
            ->assertSee('menu-footer__brand', false)
            ->assertSee('Especialidades')
            ->assertSee('Taco de la casa')
            ->assertSee('$49.50')
            ->assertSee('business/banner-menu.webp', false)
            ->assertSee('instagram.com/cocinaverde', false)
            ->assertDontSee('Producto oculto')
            ->assertDontSee('Agregar al carrito')
            ->assertDontSee('Hacer pedido');
    }

    public function test_long_descriptions_are_clamped_in_cards_and_remain_complete_in_the_detail(): void
    {
        $description = 'Una descripción deliberadamente extensa con ingredientes, preparación artesanal, recomendaciones de servicio y notas de sabor que deben conservarse completas para el detalle del producto.';

        Product::create([
            'name' => 'Especialidad con descripción extensa',
            'description' => $description,
            'price' => 120,
            'is_active' => true,
        ]);

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee($description)
            ->assertSee('data-product-detail=', false);

        $css = file_get_contents(public_path('assets/css/public-menu.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.product-card p{margin-top:7px;-webkit-line-clamp:2}', $css);
        $this->assertStringContainsString('@media(max-width:390px)', $css);
    }

    public function test_menu_uses_a_responsive_card_grid_without_compressing_the_mobile_intro(): void
    {
        $css = file_get_contents(public_path('assets/css/public-menu.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString(
            '.product-grid{grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}',
            $css
        );
        $this->assertStringContainsString('@media(max-width:1199px)', $css);
        $this->assertStringContainsString(
            '.product-grid{grid-template-columns:repeat(4,minmax(0,1fr))}',
            $css
        );
        $this->assertStringContainsString(
            '.product-grid{grid-template-columns:repeat(3,minmax(0,1fr))}',
            $css
        );
        $this->assertStringContainsString(
            '.product-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}',
            $css
        );
        $this->assertStringContainsString('.menu-search__status{grid-column:1}', $css);
        $this->assertStringContainsString('.menu-discovery .menu-search__clear{grid-column:3}', $css);
        $this->assertStringContainsString(
            '.menu-discovery .menu-search__submit{width:48px;height:48px;grid-column:4;justify-self:end;margin-right:2px',
            $css
        );
        $this->assertStringContainsString(
            '.product-card:not(.product-card--featured){height:100%;display:flex;flex-direction:column}',
            $css
        );
        $this->assertStringContainsString(
            '.product-card:not(.product-card--featured) .product-card__content{min-height:166px}',
            $css
        );
    }

    public function test_every_static_public_menu_icon_exists_in_boxicons(): void
    {
        $boxicons = file_get_contents(public_path('assets/vendor/fonts/boxicons.css'));
        $views = [
            resource_path('views/public-menu/index.blade.php'),
            resource_path('views/components/public-menu/product-card.blade.php'),
            resource_path('views/components/public-menu/brand-header.blade.php'),
        ];
        $icons = [];

        foreach ($views as $view) {
            preg_match_all('/\bbx-[a-z0-9-]+\b/', file_get_contents($view), $matches);
            $icons = array_merge($icons, $matches[0]);
        }

        foreach (array_unique($icons) as $icon) {
            $this->assertStringContainsString(".{$icon}:before", $boxicons, "El icono {$icon} no existe en Boxicons.");
        }
    }

    public function test_featured_products_follow_the_order_configured_in_the_panel(): void
    {
        $first = Product::create(['name' => 'Primero', 'price' => 20, 'is_active' => true]);
        $second = Product::create(['name' => 'Segundo', 'price' => 30, 'is_active' => true]);

        DigitalMenuSetting::current()->update([
            'featured_product_ids' => [$second->id, $first->id],
        ]);

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSeeInOrder(['Favoritos de la casa', 'Segundo', 'Primero']);
    }

    public function test_product_cards_expose_an_informational_modal_with_ingredients_and_options(): void
    {
        $product = Product::create([
            'name' => 'Hamburguesa configurable',
            'description' => 'Carne a la parrilla con pan artesanal.',
            'price' => 145,
            'is_customizable' => true,
            'min_ingredients' => 1,
            'max_ingredients' => 3,
            'max_addons' => 2,
            'is_active' => true,
        ]);
        $ingredient = Ingredient::create([
            'name' => 'Cebolla caramelizada',
            'description' => 'Preparada lentamente',
            'image' => 'ingredients/cebolla.webp',
            'extra_price' => 0,
            'is_active' => true,
        ]);
        $group = AddonGroup::create([
            'name' => 'Elige tu queso',
            'is_required' => true,
            'min_selections' => 1,
            'max_selections' => 1,
            'is_active' => true,
        ]);
        Addon::create([
            'addon_group_id' => $group->id,
            'name' => 'Queso manchego',
            'extra_price' => 15,
            'is_active' => true,
        ]);
        $product->ingredients()->attach($ingredient->id, ['sort_order' => 0]);
        $product->addonGroups()->attach($group->id, ['sort_order' => 0]);

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('id="product-detail-modal"', false)
            ->assertSee('Cebolla caramelizada')
            ->assertSee(Storage::url('ingredients/cebolla.webp'), false)
            ->assertSee('Elige tu queso')
            ->assertSee('Queso manchego')
            ->assertSee('Esta vista es informativa');
    }

    public function test_information_cards_link_to_public_hours_gallery_and_contact_pages(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('business/gallery/local.jpg', 'image');
        Storage::disk('public')->put('business/banner.jpg', 'image');
        BusinessSetting::current()->update([
            'business_name' => 'Cocina de Barrio',
            'phone' => '5512345678',
            'address' => 'Av. Principal 123',
            'instagram_url' => 'https://instagram.com/cocinadebarrio',
            'maps_url' => 'https://maps.app.goo.gl/cocina-de-barrio',
            'banner_path' => 'business/banner.jpg',
            'gallery_paths' => [
                ['path' => 'business/gallery/local.jpg', 'caption' => 'Terraza principal'],
                ['path' => 'business/gallery/missing.jpg', 'caption' => 'No debe aparecer'],
            ],
        ]);
        DigitalMenuSetting::current()->update([
            'banner_paths' => [['path' => 'business/banner.jpg', 'alt' => 'Banner principal']],
            'gallery_paths' => [
                ['path' => 'business/gallery/local.jpg', 'caption' => 'Terraza principal'],
                ['path' => 'business/gallery/missing.jpg', 'caption' => 'No debe aparecer'],
            ],
        ]);

        $homeResponse = $this->get('/')
            ->assertOk()
            ->assertSee('Reservar una mesa')
            ->assertSee(route('public.menu'), false)
            ->assertSee(route('public.hours'), false)
            ->assertSee(route('public.gallery'), false)
            ->assertSee(route('public.contact'), false);

        $this->assertMatchesRegularExpression('/Ver\s+1\s+fotografía/u', $homeResponse->getContent());

        $this->get(route('public.hours'))
            ->assertOk()
            ->assertSee('Semana completa')
            ->assertSee('Estado actual')
            ->assertSee('maps.app.goo.gl/cocina-de-barrio', false);

        $this->get(route('public.gallery'))
            ->assertOk()
            ->assertSee(Storage::url('business/banner.jpg'), false)
            ->assertSee(Storage::url('business/gallery/local.jpg'), false)
            ->assertSee('Terraza principal')
            ->assertDontSee('business/gallery/missing.jpg', false);

        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee(Storage::url('business/banner.jpg'), false)
            ->assertSee('5512345678')
            ->assertSee('maps.app.goo.gl/cocina-de-barrio', false)
            ->assertSee('instagram.com/cocinadebarrio', false);
    }

    public function test_opening_status_supports_an_overnight_schedule(): void
    {
        $business = BusinessSetting::current();
        $hours = BusinessSetting::DEFAULT_HOURS;
        $hours[4] = ['key' => 'friday', 'label' => 'Viernes', 'enabled' => true, 'opens' => '18:00', 'closes' => '02:00'];
        $business->update(['business_hours' => $hours]);

        $status = $business->openingStatus(Carbon::parse('2026-07-31 23:30:00'));

        $this->assertTrue($status['is_open']);
        $this->assertSame('Abierto ahora', $status['label']);
        $this->assertSame('Cierra a las 02:00', $status['detail']);
    }
}
