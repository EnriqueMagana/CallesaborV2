<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\DeveloperConsole;
use App\Mail\DeveloperTestMail;
use App\Models\User;
use App\Services\Firebase\FirebaseRealtimeDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Mockery;
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

    public function test_super_admin_can_inspect_and_manually_clear_firebase_notification_signals(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $initialSnapshot = [
            'ok' => true,
            'available' => true,
            'message' => 'Se encontró una señal pendiente.',
            'root' => 'notifications',
            'total' => 1,
            'shown' => 1,
            'fetched_at' => '20/08/2026 10:00:00',
            'signals' => [[
                'id' => 'notification-test-id',
                'user_uid' => 'laravel_'.$user->id,
                'event_key' => 'order.ready',
                'created_at_ms' => 1800000000000,
                'created_at' => '15/01/2027 02:00:00',
            ]],
        ];
        $emptySnapshot = array_merge($initialSnapshot, [
            'message' => 'No hay señales pendientes en Firebase.',
            'total' => 0,
            'shown' => 0,
            'signals' => [],
        ]);

        $firebase = Mockery::mock(FirebaseRealtimeDatabase::class);
        $firebase->shouldReceive('notificationSignals')->twice()->andReturn($initialSnapshot, $emptySnapshot);
        $firebase->shouldReceive('clearNotifications')->once()->with('manual_cleanup')->andReturnTrue();
        $this->app->instance(FirebaseRealtimeDatabase::class, $firebase);

        Livewire::actingAs($user)
            ->test(DeveloperConsole::class)
            ->assertSee('Señales pendientes en Firebase')
            ->assertSee('order.ready')
            ->assertSee('notification-test-id')
            ->call('confirmClearFirebaseNotifications')
            ->assertDispatched('open-confirm')
            ->call('clearFirebaseNotifications')
            ->assertSet('firebaseNotifications.total', 0)
            ->assertSet('lastAction.ok', true)
            ->assertSee('Firebase no tiene señales pendientes.');
    }

    public function test_super_admin_uses_styled_responsive_modals_instead_of_native_confirms(): void
    {
        $view = file_get_contents(resource_path('views/livewire/super-admin/developer-console.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $confirmCss = file_get_contents(public_path('assets/css/confirm-modal.css'));
        $appUiCss = file_get_contents(public_path('assets/css/app-ui.css'));

        $this->assertStringNotContainsString('wire:confirm=', $view);
        $this->assertStringContainsString('wire:click="confirmClearFirebaseNotifications"', $view);
        $this->assertStringContainsString('<livewire:ui.confirm-modal />', $layout);
        $this->assertStringContainsString('class="app-session-ended-modal"', $layout);
        $this->assertStringContainsString('.confirm-dialog-layer{', $confirmCss);
        $this->assertStringContainsString('@media(max-width:520px)', $confirmCss);
        $this->assertStringContainsString('.app-session-ended-modal {', $appUiCss);
        $this->assertStringContainsString('@media (max-width: 479.98px)', $appUiCss);
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

    public function test_super_admin_can_send_a_test_email_from_the_developer_console(): void
    {
        Mail::fake();
        config()->set('mail.default', 'resend');
        config()->set('services.resend.key', 're_test_key');
        config()->set('mail.from.address', 'sistema@dominio-verificado.test');

        $user = User::factory()->create();
        $user->assignRole('super-admin');
        RateLimiter::clear('developer-email-test:'.$user->id);

        Livewire::actingAs($user)
            ->test(DeveloperConsole::class)
            ->set('testEmailRecipient', 'destino@example.com')
            ->call('sendTestEmail')
            ->assertHasNoErrors('testEmailRecipient')
            ->assertSet('lastAction.ok', true);

        Mail::assertSent(DeveloperTestMail::class, function (DeveloperTestMail $mail): bool {
            $html = (string) $mail->render();

            $this->assertMatchesRegularExpression('/<img src="(?:data:image\/png;base64|cid:)/', $html);
            $this->assertStringContainsString('alt="Logo de '.config('app.name').'"', $html);
            $this->assertStringContainsString('El envío de correo funciona', $html);

            return $mail->hasTo('destino@example.com');
        });
    }

    public function test_test_email_requires_a_valid_recipient(): void
    {
        Mail::fake();
        config()->set('mail.default', 'resend');
        config()->set('services.resend.key', 're_test_key');
        config()->set('mail.from.address', 'sistema@dominio-verificado.test');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(DeveloperConsole::class)
            ->set('testEmailRecipient', 'correo-invalido')
            ->call('sendTestEmail')
            ->assertHasErrors(['testEmailRecipient' => 'email']);

        Mail::assertNothingSent();
    }

    public function test_test_email_rejects_the_placeholder_sender_without_sending(): void
    {
        Mail::fake();
        config()->set('mail.default', 'resend');
        config()->set('services.resend.key', 're_test_key');
        config()->set('mail.from.address', 'hello@example.com');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(DeveloperConsole::class)
            ->set('testEmailRecipient', 'destino@example.com')
            ->call('sendTestEmail')
            ->assertHasErrors('testEmailRecipient')
            ->assertSet('lastAction.ok', false);

        Mail::assertNothingSent();
    }

    public function test_test_email_reports_a_missing_resend_key_without_sending(): void
    {
        Mail::fake();
        config()->set('mail.default', 'resend');
        config()->set('services.resend.key', null);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(DeveloperConsole::class)
            ->set('testEmailRecipient', 'destino@example.com')
            ->call('sendTestEmail')
            ->assertHasErrors('testEmailRecipient')
            ->assertSet('lastAction.ok', false);

        Mail::assertNothingSent();
    }
}
