<?php

namespace Tests\Feature;

use App\Livewire\Admin\KioskSettings;
use App\Models\KioskTerminal;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KioskSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_kiosk_settings(): void
    {
        $this->get(route('app.kioscos'))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.kioscos'))
            ->assertForbidden();
    }

    public function test_owner_can_access_without_an_explicit_permission(): void
    {
        $owner = User::factory()->create();
        Role::create(['name' => 'owner', 'guard_name' => 'web']);
        $owner->assignRole('owner');

        $this->actingAs($owner)
            ->get(route('app.kioscos'))
            ->assertOk()
            ->assertSee('Configuración de kioscos');
    }

    public function test_authorized_user_can_create_a_configured_terminal_and_token(): void
    {
        $manager = User::factory()->create();
        $responsible = User::factory()->create();
        Permission::create(['name' => 'gestionar kioscos', 'guard_name' => 'web', 'group' => 'kiosco']);
        $manager->givePermissionTo('gestionar kioscos');

        $this->actingAs($manager);

        Livewire::test(KioskSettings::class)
            ->call('createTerminal')
            ->set('name', 'Kiosco terraza')
            ->set('userId', $responsible->id)
            ->set('allowDineIn', false)
            ->set('allowTakeaway', true)
            ->set('allowDelivery', true)
            ->set('requireCustomerPhone', true)
            ->set('ordersPerMinute', 12)
            ->set('autoResetSeconds', 60)
            ->call('saveTerminal')
            ->assertSet('showForm', false)
            ->assertNotSet('issuedUrl', null);

        $terminal = KioskTerminal::firstOrFail();
        $this->assertSame('Kiosco terraza', $terminal->name);
        $this->assertFalse($terminal->allow_dine_in);
        $this->assertTrue($terminal->allow_takeaway);
        $this->assertTrue($terminal->allow_delivery);
        $this->assertTrue($terminal->require_customer_phone);
        $this->assertSame(12, $terminal->orders_per_minute);
        $this->assertSame(60, $terminal->auto_reset_seconds);
        $this->assertNotNull($terminal->token_hint);
        $this->assertNotNull($terminal->token_secret);
        $this->assertSame($terminal->token_hash, hash('sha256', $terminal->token_secret));
    }

    public function test_token_rotation_uses_the_designed_confirmation_flow(): void
    {
        $manager = User::factory()->create();
        Permission::create(['name' => 'gestionar kioscos', 'guard_name' => 'web', 'group' => 'kiosco']);
        $manager->givePermissionTo('gestionar kioscos');
        $terminal = KioskTerminal::create([
            'name' => 'Kiosco entrada',
            'token_hash' => hash('sha256', 'old-token-that-is-long-enough-for-testing-123456789'),
            'user_id' => $manager->id,
            'is_active' => true,
        ]);
        $oldHash = $terminal->token_hash;

        $this->actingAs($manager);

        Livewire::test(KioskSettings::class)
            ->call('confirmRotateToken', $terminal->id)
            ->assertDispatched('open-confirm')
            ->dispatch('modal-confirmed', action: 'rotateKioskToken', params: [$terminal->id])
            ->assertNotSet('issuedUrl', null);

        $this->assertNotSame($oldHash, $terminal->fresh()->token_hash);
        $this->assertNotNull($terminal->fresh()->token_secret);
    }

    public function test_manager_can_publish_featured_products_with_real_promotional_prices(): void
    {
        $manager = User::factory()->create();
        $responsible = User::factory()->create();
        $product = Product::create([
            'name' => 'Hamburguesa premium',
            'price' => 159,
            'is_active' => true,
        ]);
        Permission::create(['name' => 'gestionar kioscos', 'guard_name' => 'web', 'group' => 'kiosco']);
        $manager->givePermissionTo('gestionar kioscos');

        $this->actingAs($manager);

        Livewire::test(KioskSettings::class)
            ->call('createTerminal')
            ->set('name', 'Kiosco promociones')
            ->set('userId', $responsible->id)
            ->set('promotionEnabled', true)
            ->set('promotionBadge', 'Favoritos del chef')
            ->set('promotionTitle', 'Prueba algo extraordinario')
            ->set('promotionMessage', 'Productos seleccionados con precio especial.')
            ->call('toggleFeaturedProduct', $product->id)
            ->set("promotionDiscounts.{$product->id}", true)
            ->set("promotionPrices.{$product->id}", 129)
            ->set("promotionLabels.{$product->id}", 'Oferta especial')
            ->call('saveTerminal')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $terminal = KioskTerminal::firstOrFail();
        $this->assertTrue($terminal->promotion_enabled);
        $this->assertSame('Favoritos del chef', $terminal->promotion_badge);
        $this->assertDatabaseHas('kiosk_product_promotions', [
            'kiosk_terminal_id' => $terminal->id,
            'product_id' => $product->id,
            'promotional_price' => 129,
            'label' => 'Oferta especial',
        ]);
    }

    public function test_featured_product_can_be_published_without_a_discount(): void
    {
        $manager = User::factory()->create();
        $responsible = User::factory()->create();
        $product = Product::create([
            'name' => 'Taco recomendado',
            'price' => 95,
            'is_active' => true,
        ]);
        Permission::create(['name' => 'gestionar kioscos', 'guard_name' => 'web', 'group' => 'kiosco']);
        $manager->givePermissionTo('gestionar kioscos');

        $this->actingAs($manager);

        Livewire::test(KioskSettings::class)
            ->call('createTerminal')
            ->set('name', 'Kiosco sin descuentos')
            ->set('userId', $responsible->id)
            ->call('toggleFeaturedProduct', $product->id)
            ->assertSet('promotionEnabled', true)
            ->set("promotionDiscounts.{$product->id}", false)
            ->set("promotionPrices.{$product->id}", 79)
            ->set("promotionLabels.{$product->id}", 'Recomendado')
            ->call('saveTerminal')
            ->assertHasNoErrors();

        $terminal = KioskTerminal::firstOrFail();
        $this->assertTrue($terminal->promotion_enabled);
        $this->assertDatabaseHas('kiosk_product_promotions', [
            'kiosk_terminal_id' => $terminal->id,
            'product_id' => $product->id,
            'promotional_price' => null,
            'label' => 'Recomendado',
        ]);
    }

    public function test_each_terminal_card_can_open_its_public_kiosk_in_a_new_tab(): void
    {
        $manager = User::factory()->create();
        Permission::create(['name' => 'gestionar kioscos', 'guard_name' => 'web', 'group' => 'kiosco']);
        $manager->givePermissionTo('gestionar kioscos');
        $legacyToken = 'legacy-token-that-is-long-enough-for-testing-123456789';
        $terminal = KioskTerminal::create([
            'name' => 'Kiosco barra',
            'token_hash' => hash('sha256', $legacyToken),
            'user_id' => $manager->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('app.kioscos'))
            ->assertOk()
            ->assertSee('Abrir kiosco')
            ->assertSee(route('app.kioscos.open', $terminal), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);

        $response = $this->get(route('app.kioscos.open', $terminal));
        $terminal->refresh();

        $this->assertNotNull($terminal->token_secret);
        $this->assertSame(hash('sha256', $terminal->token_secret), $terminal->token_hash);
        $response->assertRedirect(route('kiosk.order', ['token' => $terminal->token_secret]));
    }
}
