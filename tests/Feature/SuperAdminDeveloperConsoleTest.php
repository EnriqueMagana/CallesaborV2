<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\DeveloperConsole;
use App\Mail\DeveloperTestMail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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
