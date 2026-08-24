<?php

namespace Tests\Feature;

use App\Livewire\Admin\DigitalMenuManager;
use App\Models\DigitalMenuSetting;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DigitalMenuSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SidebarMenuSeeder::class]);
    }

    public function test_digital_menu_has_an_independent_permission_and_sidebar_module(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('app.menu-digital'))->assertForbidden();

        $user->assignRole('owner');

        $this->actingAs($user)->get(route('app.menu-digital'))
            ->assertOk()
            ->assertSee('Menú digital')
            ->assertSee('Favoritos ordenados');

        $this->assertDatabaseHas('permissions', ['name' => 'gestionar menu digital']);
        $this->assertDatabaseHas('sidebar_menu_items', [
            'system_key' => 'restaurant.digital-menu',
            'route_name' => 'app.menu-digital',
        ]);
    }

    public function test_owner_can_order_favorites_upload_banners_and_control_public_sections(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $first = Product::create(['name' => 'Primero', 'price' => 90, 'is_active' => true]);
        $second = Product::create(['name' => 'Segundo', 'price' => 120, 'is_active' => true]);

        $this->actingAs($owner);

        Livewire::test(DigitalMenuManager::class)
            ->set('categoryStyle', 'circles')
            ->set('showGallery', false)
            ->set('bannerIntervalSeconds', 7)
            ->set('bannerUploads', [
                UploadedFile::fake()->image('uno.jpg', 1600, 640),
                UploadedFile::fake()->image('dos.jpg', 1600, 640),
            ])
            ->set('bannerUploadAlts', ['Promoción uno', 'Promoción dos'])
            ->call('toggleFeaturedProduct', $first->id)
            ->call('toggleFeaturedProduct', $second->id)
            ->call('moveFeatured', 1, -1)
            ->call('save')
            ->assertHasNoErrors();

        $setting = DigitalMenuSetting::current()->fresh();

        $this->assertSame([$second->id, $first->id], $setting->featured_product_ids);
        $this->assertSame('circles', $setting->category_style);
        $this->assertFalse($setting->show_gallery);
        $this->assertSame(7, $setting->banner_interval_seconds);
        $this->assertCount(2, $setting->bannerItems());
        Storage::disk('public')->assertExists($setting->bannerItems()[0]['path']);

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee('data-menu-banner-carousel', false)
            ->assertSee('category-nav--circles', false)
            ->assertSee('Favorito número 1')
            ->assertSeeInOrder(['Favoritos de la casa', 'Segundo', 'Primero'])
            ->assertDontSee('Nuestros espacios');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('href="#galeria"', false);
        $this->get(route('public.gallery'))->assertNotFound();
    }

    public function test_category_style_and_gallery_visibility_apply_to_every_public_entry_point(): void
    {
        $category = \App\Models\Category::create([
            'name' => 'Especiales',
            'icon' => 'bx-dish',
            'color' => '#15803d',
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Platillo especial',
            'price' => 150,
            'is_active' => true,
        ]);
        DigitalMenuSetting::current()->update([
            'category_style' => 'circles',
            'show_categories' => true,
            'show_gallery' => false,
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('home-categories--circles', false)
            ->assertSee('home-category-card--circle', false)
            ->assertDontSee('href="#galeria"', false);

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee('category-nav--circles', false)
            ->assertSee('menu-info-links__grid--without-gallery', false)
            ->assertDontSee('menu-info-card--gallery', false)
            ->assertDontSee('<i class="bx bx-chevron-right" aria-hidden="true"></i>Galería', false);

        $this->get(route('public.gallery'))->assertNotFound();
    }

    public function test_owner_can_open_every_digital_menu_section_without_render_errors(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $component = Livewire::actingAs($owner)->test(DigitalMenuManager::class);

        foreach (['banners', 'featured', 'categories', 'gallery', 'overview'] as $section) {
            $component->call('setSection', $section)
                ->assertSet('activeSection', $section)
                ->assertStatus(200);
        }
    }

    public function test_every_digital_menu_manager_icon_exists_in_boxicons(): void
    {
        $boxicons = file_get_contents(public_path('assets/vendor/fonts/boxicons.css'));
        $view = file_get_contents(resource_path('views/livewire/admin/digital-menu-manager.blade.php'));
        preg_match_all('/\bbx-[a-z0-9-]+\b/', $view, $matches);

        foreach (array_unique($matches[0]) as $icon) {
            $this->assertStringContainsString(".{$icon}:before", $boxicons, "El icono {$icon} no existe en Boxicons.");
        }
    }

    public function test_section_switches_keep_a_responsive_three_part_layout(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/digital-menu-manager.blade.php'));
        $styles = file_get_contents(public_path('assets/css/business-settings.css'));

        $this->assertSame(3, substr_count($view, 'class="digital-menu-section-switch__icon"'));
        $this->assertSame(3, substr_count($view, 'class="digital-menu-section-switch__copy"'));
        $this->assertStringContainsString('.digital-menu-section-switch__copy{min-width:0;display:grid;gap:3px}', $styles);
        $this->assertStringContainsString('.digital-menu-toggle,.digital-menu-section-switch{grid-template-columns:42px minmax(0,1fr) 44px', $styles);
    }
}
