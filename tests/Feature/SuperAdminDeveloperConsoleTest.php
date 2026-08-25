<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\DeveloperConsole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminDeveloperConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SidebarMenuSeeder::class);
    }

    public function test_super_admin_can_open_the_developer_console(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $this->actingAs($user)
            ->get(route('app.super-admin'))
            ->assertOk()
            ->assertSee('Centro técnico');
    }

    public function test_regular_admin_cannot_open_the_developer_console_or_pulse(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get(route('app.super-admin'))->assertForbidden();
        $this->actingAs($user)->get(route('pulse'))->assertForbidden();
    }

    public function test_livewire_test_creates_a_notification_for_the_current_super_admin(): void
    {
        config()->set('firebase.realtime.enabled', false);
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(DeveloperConsole::class)
            ->call('testLivewireNotification')
            ->assertDispatched('notifications-check');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'event_key' => 'developer.livewire_test',
        ]);
    }

    public function test_pulse_sidebar_link_uses_a_full_page_load(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('pulse').'" class="menu-link"', false)
            ->assertDontSee('href="'.route('pulse').'" class="menu-link" wire:navigate', false);
    }
}
