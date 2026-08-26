<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertSeeVolt('pages.auth.forgot-password')
            ->assertStatus(200);
    }

    public function test_login_shows_a_dedicated_password_recovery_action(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('¿No puedes acceder?')
            ->assertSee('Recuperar contraseña')
            ->assertSee(route('password.request'), false);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_email_receives_the_same_neutral_response(): void
    {
        Notification::fake();

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'no-existe@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee('Si existe una cuenta con ese correo');

        Notification::assertNothingSent();
    }

    public function test_password_reset_email_is_branded_and_uses_the_secure_route(): void
    {
        $user = User::factory()->create(['name' => 'María']);
        $notification = new ResetPasswordNotification('token-seguro');
        $mail = $notification->toMail($user);

        $this->assertSame('Recupera el acceso a '.config('app.name'), $mail->subject);
        $this->assertStringContainsString('/reset-password/token-seguro', $mail->actionUrl);
        $this->assertStringContainsString(urlencode($user->email), $mail->actionUrl);

        $html = (string) $mail->render();

        $this->assertMatchesRegularExpression('/<img src="(?:data:image\/png;base64|cid:)/', $html);
        $this->assertStringContainsString('alt="Logo de '.config('app.name').'"', $html);
        $this->assertStringContainsString('Restablece tu contraseña', $html);
        $this->assertStringContainsString('Hola, María:', $html);
        $this->assertStringContainsString('Restablecer contraseña', $html);
        $this->assertStringContainsString('/reset-password/token-seguro', $html);
    }

    public function test_resend_mailer_builds_the_official_laravel_transport(): void
    {
        config(['services.resend.key' => 're_test_placeholder']);

        $transport = app('mail.manager')->mailer('resend')->getSymfonyTransport();

        $this->assertInstanceOf(ResendTransport::class, $transport);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response
                ->assertSeeVolt('pages.auth.reset-password')
                ->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $component = Volt::test('pages.auth.reset-password', ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'password')
                ->set('password_confirmation', 'password');

            $component->call('resetPassword');

            $component
                ->assertRedirect('/login')
                ->assertHasNoErrors();

            return true;
        });
    }
}
