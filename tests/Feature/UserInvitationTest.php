<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserList;
use App\Livewire\Auth\AcceptUserInvitation;
use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('mail.default', 'array');
        config()->set('mail.from.address', 'equipo@calle-sabor.test');
    }

    public function test_authorized_user_can_send_a_one_hour_role_bound_invitation(): void
    {
        Mail::fake();
        $now = CarbonImmutable::parse('2026-08-26 10:00:00');
        $this->travelTo($now);
        $owner = $this->owner();
        RateLimiter::clear('send-user-invitation:'.$owner->id);

        Livewire::actingAs($owner)
            ->test(UserList::class)
            ->set('inviteEmail', 'nuevo@equipo.test')
            ->set('inviteRole', 'cajero')
            ->call('sendUserInvitation')
            ->assertHasNoErrors()
            ->assertSet('showInvitationPanel', false)
            ->assertDispatched('notify');

        $invitation = UserInvitation::query()->where('email', 'nuevo@equipo.test')->firstOrFail();
        $this->assertSame('cajero', $invitation->role->name);
        $this->assertSame(3600.0, $now->diffInSeconds($invitation->expires_at, false));
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertNull($invitation->accepted_at);

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail): bool {
            $html = (string) $mail->render();

            $this->assertStringContainsString('Cajero', $html);
            $this->assertStringContainsString('Este enlace vence en 1 hora', $html);
            $this->assertStringContainsString('Completar mi registro', $html);
            $this->assertMatchesRegularExpression('/<img src="(?:data:image\/png;base64|cid:)/', $html);

            return $mail->hasTo('nuevo@equipo.test');
        });
    }

    public function test_user_without_create_permission_cannot_send_invitations(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('ver usuarios');

        Livewire::actingAs($viewer)
            ->test(UserList::class)
            ->set('inviteEmail', 'nuevo@equipo.test')
            ->set('inviteRole', 'cajero')
            ->call('sendUserInvitation')
            ->assertForbidden();

        $this->assertDatabaseCount('user_invitations', 0);
    }

    public function test_mail_provider_failure_is_reported_without_leaving_an_active_invitation(): void
    {
        $owner = $this->owner();
        RateLimiter::clear('send-user-invitation:'.$owner->id);
        Mail::shouldReceive('to')->once()->with('fallo@equipo.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Provider unavailable'));

        Livewire::actingAs($owner)
            ->test(UserList::class)
            ->call('openInvitationPanel')
            ->set('inviteEmail', 'fallo@equipo.test')
            ->set('inviteRole', 'cajero')
            ->call('sendUserInvitation')
            ->assertHasErrors('inviteEmail')
            ->assertSet('showInvitationPanel', true);

        $this->assertDatabaseMissing('user_invitations', ['email' => 'fallo@equipo.test']);
    }

    public function test_missing_invitation_migration_is_reported_without_a_server_error(): void
    {
        $owner = $this->owner();
        RateLimiter::clear('send-user-invitation:'.$owner->id);
        Schema::shouldReceive('hasTable')->once()->with('user_invitations')->andReturnFalse();

        Livewire::actingAs($owner)
            ->test(UserList::class)
            ->call('openInvitationPanel')
            ->set('inviteEmail', 'pendiente@equipo.test')
            ->set('inviteRole', 'cajero')
            ->call('sendUserInvitation')
            ->assertHasErrors('inviteEmail')
            ->assertSee('pendiente de actualizar');
    }

    public function test_invited_person_can_complete_the_wizard_with_the_preassigned_role_and_optional_photo(): void
    {
        Storage::fake('public');
        [$invitation, $token] = $this->invitation('persona@equipo.test', 'mesero');
        $photo = UploadedFile::fake()->createWithContent(
            'avatar.png',
            file_get_contents(public_path('assets/img/logo.png')),
        );

        Livewire::test(AcceptUserInvitation::class, [
            'invitation' => $invitation,
            'token' => $token,
        ])
            ->assertSet('invitationValid', true)
            ->assertSee('Mesero')
            ->set('photo', $photo)
            ->set('name', 'María del Mar')
            ->set('phone', '+52 999 123 4567')
            ->set('password', 'password-seguro')
            ->set('password_confirmation', 'password-seguro')
            ->call('completeRegistration')
            ->assertHasNoErrors()
            ->assertSet('registrationComplete', true);

        $user = User::query()->where('email', 'persona@equipo.test')->firstOrFail();
        $this->assertSame('María del Mar', $user->name);
        $this->assertSame('+52 999 123 4567', $user->phone);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('mesero'));
        $this->assertFalse($user->hasAnyRole(['owner', 'super-admin']));
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame($user->id, $invitation->accepted_user_id);

        Livewire::test(AcceptUserInvitation::class, [
            'invitation' => $invitation,
            'token' => $token,
        ])->assertSet('invitationValid', false)->assertSee('ya fue utilizada');
    }

    public function test_invitation_is_rejected_at_exactly_one_hour_even_with_the_correct_token(): void
    {
        $now = CarbonImmutable::parse('2026-08-26 12:00:00');
        $this->travelTo($now);
        [$invitation, $token] = $this->invitation('tarde@equipo.test', 'cajero', $now->addHour());

        $this->travelTo($now->addHour());

        Livewire::test(AcceptUserInvitation::class, [
            'invitation' => $invitation->fresh(),
            'token' => $token,
        ])
            ->assertSet('invitationValid', false)
            ->assertSee('invitación venció')
            ->set('name', 'Intento Tardío')
            ->set('password', 'password-seguro')
            ->set('password_confirmation', 'password-seguro')
            ->call('completeRegistration');

        $this->assertDatabaseMissing('users', ['email' => 'tarde@equipo.test']);
    }

    public function test_reinviting_the_same_email_invalidates_the_previous_link(): void
    {
        Mail::fake();
        $owner = $this->owner();
        RateLimiter::clear('send-user-invitation:'.$owner->id);

        Livewire::actingAs($owner)->test(UserList::class)
            ->set('inviteEmail', 'reenvio@equipo.test')
            ->set('inviteRole', 'cajero')
            ->call('sendUserInvitation');

        $firstMail = Mail::sent(UserInvitationMail::class)->first();
        $firstUrl = $firstMail->invitationUrl;
        $firstHash = UserInvitation::query()->where('email', 'reenvio@equipo.test')->value('token_hash');

        Livewire::actingAs($owner)->test(UserList::class)
            ->set('inviteEmail', 'reenvio@equipo.test')
            ->set('inviteRole', 'mesero')
            ->call('sendUserInvitation');

        $invitation = UserInvitation::query()->where('email', 'reenvio@equipo.test')->firstOrFail();
        $this->assertNotSame($firstHash, $invitation->token_hash);
        $this->assertSame('mesero', $invitation->role->name);
        $this->get($firstUrl)->assertOk()->assertSee('El enlace de invitación no es válido');
    }

    /** @return array{UserInvitation, string} */
    private function invitation(string $email, string $roleName, mixed $expiresAt = null): array
    {
        $token = Str::random(64);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $invitation = UserInvitation::query()->create([
            'email' => $email,
            'role_id' => $role->id,
            'token_hash' => UserInvitation::hashToken($token),
            'invited_by' => $this->owner()->id,
            'expires_at' => $expiresAt ?? now()->addHour(),
        ]);

        return [$invitation, $token];
    }

    private function owner(): User
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        return $owner;
    }
}
