<?php

namespace Tests\Feature;

use App\Livewire\Admin\BusinessSettingsManager;
use App\Models\Area;
use App\Models\BusinessSetting;
use App\Models\CashRegister;
use App\Models\CashRegisterCut;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketTemplate;
use App\Models\User;
use App\Services\ThermalTicketRenderer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_permission_is_created_without_being_granted_to_admin_automatically(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'gestionar configuracion negocio']);
        $this->assertFalse(Role::findByName('admin')->hasPermissionTo('gestionar configuracion negocio'));
    }

    public function test_only_an_authorized_user_can_open_business_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('app.configuracion-negocio'))->assertForbidden();

        $user->assignRole('owner');
        $this->actingAs($user)->get(route('app.configuracion-negocio'))
            ->assertOk()
            ->assertSee('Ticket Maker');
    }

    public function test_owner_can_save_business_data_and_a_ticket_template(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner);

        Livewire::test(BusinessSettingsManager::class)
            ->set('businessName', 'Calle Sabor Centro')
            ->set('platformName', 'Calle Sabor POS')
            ->set('rfc', 'csb010101abc')
            ->set('mapsUrl', 'https://maps.app.goo.gl/calle-sabor')
            ->call('saveBusiness')
            ->assertHasNoErrors()
            ->call('setTab', 'tickets')
            ->set('paperWidth', 58)
            ->set('fontSize', 14)
            ->set('showQr', true)
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_settings', [
            'business_name' => 'Calle Sabor Centro',
            'platform_name' => 'Calle Sabor POS',
            'rfc' => 'CSB010101ABC',
            'maps_url' => 'https://maps.app.goo.gl/calle-sabor',
        ]);
        $this->assertDatabaseHas('ticket_templates', [
            'key' => 'customer',
            'paper_width_mm' => 58,
            'font_size' => 14,
            'show_qr' => true,
        ]);
    }

    public function test_renderer_uses_blocks_business_data_and_qr_without_inline_css(): void
    {
        $business = BusinessSetting::current();
        $business->update(['business_name' => 'Prueba Térmica', 'rfc' => 'PTR010101AA1']);

        $attributes = TicketTemplate::defaultsFor('customer');
        $attributes['show_qr'] = true;
        $attributes['blocks'] = collect($attributes['blocks'])->map(function (array $block): array {
            if ($block['key'] === 'qr') {
                $block['enabled'] = true;
            }

            return $block;
        })->all();

        $html = app(ThermalTicketRenderer::class)->renderPreview(
            'customer',
            new TicketTemplate($attributes),
            $business,
        );

        $this->assertStringContainsString('Prueba Térmica', $html);
        $this->assertStringContainsString('PTR010101AA1', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('assets/css/ticket-print.css', $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    public function test_kitchen_area_ticket_includes_table_and_order_and_item_notes(): void
    {
        $preview = app(ThermalTicketRenderer::class)->renderPreview(
            'kitchen_area',
            new TicketTemplate(TicketTemplate::defaultsFor('kitchen_area')),
            BusinessSetting::current(),
        );

        $this->assertStringContainsString('Mesa 4', $preview);
        $this->assertStringContainsString('Entregar todos los platillos juntos.', $preview);
        $this->assertStringContainsString('Sin cebolla; término medio.', $preview);

        $user = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja cocina',
            'opened_by' => $user->id,
            'initial_amount' => 500,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $area = Area::create(['name' => 'Terraza']);
        $mesa = Mesa::create([
            'area_id' => $area->id,
            'number' => 12,
            'capacity' => 4,
            'status' => 'ocupada',
        ]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $mesa->id,
            'served_by' => $user->id,
            'type' => 'mesa',
            'status' => 'pendiente',
            'subtotal' => 145,
            'total' => 145,
            'notes' => 'Servir todos los platillos al mismo tiempo.',
        ]);
        $order->items()->create([
            'product_name' => 'Hamburguesa especial',
            'product_price' => 145,
            'quantity' => 1,
            'subtotal' => 145,
            'notes' => 'Sin cebolla y término medio.',
        ]);

        $html = app(ThermalTicketRenderer::class)->renderOrder($order, 'kitchen_area', autoPrint: false);

        $this->assertStringContainsString('Mesa 12', $html);
        $this->assertStringContainsString('Terraza', $html);
        $this->assertStringContainsString('Servir todos los platillos al mismo tiempo.', $html);
        $this->assertStringContainsString('Sin cebolla y término medio.', $html);
    }

    public function test_cash_cut_ticket_has_editable_sections_and_a_complete_preview(): void
    {
        $attributes = TicketTemplate::defaultsFor('cash_cut');
        $keys = collect($attributes['blocks'])->pluck('key')->all();

        $this->assertSame([
            'header', 'business', 'cut_meta', 'cut_sales_channels', 'cut_payment_methods',
            'cut_cash_movements', 'cut_reconciliation', 'cut_notes', 'footer',
        ], $keys);

        $html = app(ThermalTicketRenderer::class)->renderPreview(
            'cash_cut',
            new TicketTemplate($attributes),
            BusinessSetting::current(),
        );

        $this->assertStringContainsString('VENTAS POR CANAL', $html);
        $this->assertStringContainsString('RESUMEN POR FORMA DE PAGO', $html);
        $this->assertStringContainsString('MOVIMIENTOS DE EFECTIVO', $html);
        $this->assertStringContainsString('CONCILIACI', $html);
        $this->assertStringContainsString('Caja principal', $html);
        $this->assertStringContainsString('Diferencia', $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    public function test_saved_cash_cut_template_controls_the_real_printed_document(): void
    {
        $user = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja Ticket Maker',
            'opened_by' => $user->id,
            'initial_amount' => 500,
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'is_open' => false,
            'closing_notes' => 'Cierre validado',
        ]);
        $cut = CashRegisterCut::create([
            'cash_register_id' => $register->id,
            'generated_by' => $user->id,
            'folio' => 'COR-PRUEBA',
            'v_efectivo' => 750,
            'initial_amount' => 500,
            'total_cash_in' => 750,
            'expected_cash' => 1250,
            'declared_cash' => 1240,
            'difference' => -10,
            'generated_at' => now(),
        ]);

        $template = TicketTemplate::current('cash_cut');
        $template->update([
            'paper_width_mm' => 58,
            'font_size' => 15,
            'margin_mm' => 2,
            'footer_text' => 'CORTE CONFIGURADO EN TICKET MAKER',
            'blocks' => [
                ['key' => 'footer', 'label' => 'Pie del ticket', 'enabled' => true],
                ['key' => 'cut_reconciliation', 'label' => 'Conciliación y diferencia', 'enabled' => true],
                ['key' => 'header', 'label' => 'Encabezado y logo', 'enabled' => true],
                ['key' => 'cut_sales_channels', 'label' => 'Ventas por canal', 'enabled' => false],
            ],
        ]);

        $html = app(ThermalTicketRenderer::class)->renderCashCut($cut);

        $this->assertStringContainsString('ticket-paper-58 ticket-font-15 ticket-margin-2', $html);
        $this->assertStringNotContainsString('VENTAS POR CANAL', $html);
        $this->assertStringContainsString('window.print()', $html);
        $this->assertLessThan(
            strpos($html, 'Diferencia'),
            strpos($html, 'CORTE CONFIGURADO EN TICKET MAKER'),
        );
        $this->assertLessThan(
            strpos($html, 'class="ticket-header"'),
            strpos($html, 'class="ticket-cut-reconciliation"'),
        );
    }

    public function test_public_kiosk_inherits_the_restaurant_name_and_logo(): void
    {
        Storage::fake('public');
        $business = BusinessSetting::current();
        $business->update([
            'business_name' => 'Restaurante La Terraza',
            'logo_path' => 'business/logo-kiosk.png',
        ]);

        $this->get('/kiosco/token-invalido')
            ->assertOk()
            ->assertSee('Restaurante La Terraza')
            ->assertSee(Storage::url('business/logo-kiosk.png'), false);
    }

    public function test_owner_can_save_branch_hours_and_get_an_image_preview_before_saving(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner);

        $hours = BusinessSetting::DEFAULT_HOURS;
        $hours[0]['opens'] = '08:30';
        $hours[6]['enabled'] = true;

        Livewire::test(BusinessSettingsManager::class)
            ->set('businessHours', $hours)
            ->set('logoUpload', UploadedFile::fake()->image('logo.png', 600, 300))
            ->call('setBusinessSection', 'visual')
            ->assertSee('Vista previa lista para guardar')
            ->call('saveBusiness')
            ->assertHasNoErrors();

        $setting = BusinessSetting::current()->fresh();
        $this->assertSame('08:30', $setting->business_hours[0]['opens']);
        $this->assertTrue($setting->business_hours[6]['enabled']);
        Storage::disk('public')->assertExists($setting->logo_path);
    }

    public function test_owner_can_configure_the_public_menu_modules(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $product = Product::create(['name' => 'Favorito de prueba', 'price' => 95, 'is_active' => true]);

        $this->actingAs($owner);

        Livewire::test(BusinessSettingsManager::class)
            ->set('primaryColor', '#166534')
            ->set('instagramUrl', 'https://instagram.com/callesabor')
            ->set('tiktokUrl', 'https://tiktok.com/@callesabor')
            ->set('featuredProductIds', [(string) $product->id])
            ->set('galleryUploads', [
                UploadedFile::fake()->image('ambiente.jpg', 1200, 900),
                UploadedFile::fake()->image('platillo.jpg', 1200, 900),
            ])
            ->call('saveBusiness')
            ->assertHasNoErrors();

        $setting = BusinessSetting::current()->fresh();
        $this->assertSame('#166534', $setting->primary_color);
        $this->assertSame('https://instagram.com/callesabor', $setting->instagram_url);
        $this->assertSame([$product->id], $setting->featured_product_ids);
        $this->assertCount(2, $setting->gallery_paths);
        Storage::disk('public')->assertExists($setting->gallery_paths[0]['path']);
    }

    public function test_public_menu_color_must_preserve_readable_contrast(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner);

        Livewire::test(BusinessSettingsManager::class)
            ->set('primaryColor', '#f5f5f5')
            ->call('saveBusiness')
            ->assertHasErrors(['primaryColor']);
    }

    public function test_gallery_can_be_saved_independently_from_the_business_form(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner);

        Livewire::test(BusinessSettingsManager::class)
            ->set('galleryUploads', [
                UploadedFile::fake()->image('comedor.jpg', 1200, 900),
                UploadedFile::fake()->image('terraza.jpg', 1200, 900),
            ])
            ->set('galleryUploadCaptions', ['Comedor principal', 'Terraza al atardecer'])
            ->call('saveGallery')
            ->assertHasNoErrors()
            ->assertSet('galleryUploads', [])
            ->assertSet('galleryUploadCaptions', []);

        $setting = BusinessSetting::current()->fresh();
        $this->assertCount(2, $setting->gallery_paths);
        $this->assertSame('Comedor principal', $setting->gallery_paths[0]['caption']);
        $this->assertSame('Terraza al atardecer', $setting->gallery_paths[1]['caption']);
        Storage::disk('public')->assertExists($setting->gallery_paths[0]['path']);
        Storage::disk('public')->assertExists($setting->gallery_paths[1]['path']);
    }

    public function test_gallery_preserves_legacy_paths_and_allows_caption_editing_and_deletion(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('business/gallery/legacy.jpg', 'image');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        BusinessSetting::current()->update(['gallery_paths' => ['business/gallery/legacy.jpg']]);
        $this->actingAs($owner);

        Livewire::test(BusinessSettingsManager::class)
            ->assertSet('galleryPaths.0.path', 'business/gallery/legacy.jpg')
            ->set('galleryPaths.0.caption', 'Nuestra primera sucursal')
            ->call('saveGallery')
            ->assertHasNoErrors();

        $setting = BusinessSetting::current()->fresh();
        $this->assertSame('Nuestra primera sucursal', $setting->gallery_paths[0]['caption']);

        Livewire::test(BusinessSettingsManager::class)
            ->call('removeGalleryImage', 0)
            ->assertHasNoErrors();

        Storage::disk('public')->assertMissing('business/gallery/legacy.jpg');
        $this->assertSame([], BusinessSetting::current()->fresh()->gallery_paths);
    }

    public function test_gallery_accepts_more_than_eight_images(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner);
        $uploads = collect(range(1, 9))
            ->map(fn (int $number) => UploadedFile::fake()->image("gallery-{$number}.jpg", 320, 240))
            ->all();

        Livewire::test(BusinessSettingsManager::class)
            ->set('galleryUploads', $uploads)
            ->call('saveGallery')
            ->assertHasNoErrors();

        $this->assertCount(9, BusinessSetting::current()->fresh()->gallery_paths);
    }

    public function test_owner_can_apply_accessible_hour_presets(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner);

        Livewire::test(BusinessSettingsManager::class)
            ->call('setHoursPreset', 'weekdays')
            ->assertSet('businessHours.0.enabled', true)
            ->assertSet('businessHours.4.enabled', true)
            ->assertSet('businessHours.5.enabled', false)
            ->assertSet('businessHours.6.enabled', false)
            ->call('setHoursPreset', 'everyday')
            ->assertSet('businessHours.6.enabled', true);
    }
}
