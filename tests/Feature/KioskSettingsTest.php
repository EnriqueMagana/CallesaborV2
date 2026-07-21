<?php

namespace Tests\Feature;

use App\Livewire\Admin\KioskSettings;
use App\Models\KioskTerminal;
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
    }
}
