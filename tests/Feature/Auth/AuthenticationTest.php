<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\BusinessSetting;
use App\Livewire\Layout\AdminNavbar;
use App\Services\SingleSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
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
            ->assertSee('5551234567')
            ->assertSee('Acceso protegido')
            ->assertSee('Una cuenta, un navegador activo.');
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
        $token = session(SingleSessionManager::SESSION_KEY);
        $this->assertIsString($token);
        $this->assertSame((string) $user->getKey(), session(SingleSessionManager::SESSION_USER_KEY));
        $this->assertTrue(hash_equals(
            $user->fresh()->active_session_token_hash,
            hash('sha256', $token),
        ));
    }

    public function test_a_second_browser_requires_confirmation_before_invalidating_the_previous_session(): void
    {
        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $firstToken = session(SingleSessionManager::SESSION_KEY);

        $secondBrowser = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertSet('showSessionConfirmation', true)
            ->assertSee('Ya tienes una sesión iniciada en otro dispositivo')
            ->assertNoRedirect();

        $this->assertSame($firstToken, session(SingleSessionManager::SESSION_KEY));

        $secondBrowser
            ->call('confirmSessionTakeover')
            ->assertHasNoErrors()
            ->assertRedirect(route('app.dashboard', absolute: false));

        $secondToken = session(SingleSessionManager::SESSION_KEY);
        $this->assertNotSame($firstToken, $secondToken);

        $user->refresh();

        $this->withSession([SingleSessionManager::SESSION_KEY => $firstToken])
            ->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('auth_warning');

        $this->assertGuest();
    }

    public function test_active_session_confirmation_can_be_closed_without_replacing_it(): void
    {
        $user = User::factory()->create();
        $existingSession = new Store('existing-browser', new ArraySessionHandler(120));
        $existingSession->start();

        app(SingleSessionManager::class)->start($user, $existingSession);
        $originalHash = $user->fresh()->active_session_token_hash;

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertSet('showSessionConfirmation', true)
            ->call('cancelSessionTakeover')
            ->assertSet('showSessionConfirmation', false)
            ->assertSet('form.password', '')
            ->assertNoRedirect();

        $this->assertGuest();
        $this->assertSame($originalHash, $user->fresh()->active_session_token_hash);
    }

    public function test_replaced_livewire_session_receives_modal_response_instead_of_an_error_page(): void
    {
        $user = User::factory()->create();
        $oldSession = new Store('old-browser', new ArraySessionHandler(120));
        $oldSession->start();
        $oldToken = app(SingleSessionManager::class)->start($user, $oldSession);

        $newSession = new Store('new-browser', new ArraySessionHandler(120));
        $newSession->start();
        app(SingleSessionManager::class)->start($user, $newSession);

        $user->refresh();

        $this->withHeader('X-Livewire', '')
            ->withSession([SingleSessionManager::SESSION_KEY => $oldToken])
            ->actingAs($user)
            ->post('/livewire/update', [])
            ->assertStatus(409)
            ->assertJson([
                'reason' => 'session_replaced',
                'login_url' => route('login'),
            ]);

        $this->assertGuest();
    }

    public function test_admin_layout_contains_the_replaced_session_modal_and_request_hook(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $script = file_get_contents(public_path('assets/js/main.js'));

        $this->assertStringContainsString('id="app-session-ended-modal"', $layout);
        $this->assertStringContainsString('Aceptar e ir al login', $layout);
        $this->assertStringContainsString("Livewire.hook('request'", $script);
        $this->assertStringContainsString("payload.reason !== 'session_replaced'", $script);
        $this->assertStringContainsString("if (status !== 419) return;", $script);
        $this->assertStringContainsString("'/auth/session-status'", $script);
    }

    public function test_rotating_the_active_token_does_not_delete_the_previous_session_before_it_can_close_cleanly(): void
    {
        $service = file_get_contents(app_path('Services/SingleSessionManager.php'));

        $this->assertStringNotContainsString('deleteOtherDatabaseSessions', $service);
        $this->assertStringNotContainsString("DB::table", $service);
    }

    public function test_session_status_endpoint_distinguishes_current_and_guest_browsers(): void
    {
        $this->get(route('auth.session-status'))
            ->assertOk()
            ->assertJson(['authenticated' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk();

        $this->getJson(route('auth.session-status'))
            ->assertOk()
            ->assertJson(['authenticated' => true]);
    }

    public function test_an_old_token_cannot_reclaim_the_account_after_the_current_browser_logs_out(): void
    {
        $user = User::factory()->create();
        $oldSession = new Store('old-browser', new ArraySessionHandler(120));
        $oldSession->start();
        $oldToken = app(SingleSessionManager::class)->start($user, $oldSession);

        $user->forceFill(['active_session_token_hash' => null])->saveQuietly();
        $user->refresh();

        $this->withSession([SingleSessionManager::SESSION_KEY => $oldToken])
            ->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('auth_warning');

        $this->assertGuest();
        $this->assertNull($user->fresh()->active_session_token_hash);
    }

    public function test_login_uses_a_full_navigation_when_switching_to_the_admin_layout(): void
    {
        $source = file_get_contents(resource_path('views/livewire/pages/auth/login.blade.php'));

        $this->assertStringContainsString(
            "\$this->redirectIntended(default: route('app.dashboard', absolute: false), navigate: false);",
            $source,
        );
    }

    public function test_user_dropdown_is_layered_above_the_dashboard_welcome_card(): void
    {
        $appUi = file_get_contents(public_path('assets/css/app-ui.css'));
        $dashboard = file_get_contents(public_path('assets/css/dashboard.css'));

        $this->assertStringContainsString('z-index: 1050 !important;', $appUi);
        $this->assertStringContainsString('.app-navbar-user-menu', $appUi);
        $this->assertStringContainsString('z-index: 1060;', $appUi);
        $this->assertStringContainsString('.dashboard-welcome { position:relative; z-index:5;', $dashboard);
    }

    public function test_admin_layout_is_temporarily_limited_to_light_mode(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk()
            ->assertSee('<meta name="color-scheme" content="light">', false)
            ->assertSee('class="light-style layout-menu-fixed"', false)
            ->assertDontSee('assets/js/theme.js', false)
            ->assertDontSee('assets/css/dark-theme.css', false)
            ->assertDontSee('data-theme-toggle', false)
            ->assertDontSee('Modo oscuro');
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

        $this->actingAs($user)->get(route('app.dashboard'))->assertOk();

        $this->assertNotNull($user->fresh()->active_session_token_hash);

        Livewire::actingAs($user)
            ->test(AdminNavbar::class)
            ->call('logout')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->active_session_token_hash);
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
