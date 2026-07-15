<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Volt\Volt;
use Mockery\MockInterface;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_two_factor_setup_from_profile(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('profile.two-factor-authentication-form')
            ->set('enablePassword', 'password')
            ->call('enableTwoFactorAuthentication')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_user_with_two_factor_enabled_is_redirected_to_challenge(): void
    {
        $user = $this->twoFactorUser();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('two-factor.login', absolute: false));

        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_valid_two_factor_code_completes_login(): void
    {
        $user = $this->twoFactorUser();

        $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')->once()->with('test-secret', '123456')->andReturnTrue();
        });

        $response = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->post(route('two-factor.login.store'), ['code' => '123456']);

        $response->assertRedirect('/app');
        $this->assertAuthenticatedAs($user);
    }

    private function twoFactorUser(): User
    {
        $user = User::factory()->create()->forceFill([
            'two_factor_secret' => app('encrypter')->encrypt('test-secret'),
            'two_factor_recovery_codes' => app('encrypter')->encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $user->save();

        return $user;
    }
}
