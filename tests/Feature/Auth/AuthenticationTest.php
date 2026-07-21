<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\BusinessSetting;
use App\Livewire\Layout\AdminNavbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        BusinessSetting::current()->update([
            'business_name' => 'Restaurante Prueba',
            'platform_name' => 'Panel Prueba',
            'phone' => '5551234567',
        ]);

        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login')
            ->assertSee('Panel Prueba')
            ->assertSee('Restaurante Prueba')
            ->assertSee('5551234567');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('app.dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('app.dashboard', absolute: false));

        $this->get(route('app.dashboard'))->assertOk();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_admin_logout_uses_one_livewire_action_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AdminNavbar::class)
            ->call('logout')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_private_pages_are_not_cached_and_cannot_be_reopened_after_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        Livewire::actingAs($user)->test(AdminNavbar::class)->call('logout');

        $this->get(route('app.dashboard'))
            ->assertRedirect(route('login'));
    }
}
