<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_index_requires_the_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('app.usuarios'))->assertForbidden();

        $user->givePermissionTo('ver usuarios');
        $this->actingAs($user)->get(route('app.usuarios'))->assertOk()->assertSee('Equipo registrado');
    }

    public function test_creating_a_user_requires_and_assigns_an_initial_role(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $component = Livewire::test(UserList::class)
            ->set('createName', 'Ana López')
            ->set('createEmail', 'ana@example.test')
            ->set('createPassword', 'password-seguro')
            ->set('createPasswordCon', 'password-seguro')
            ->call('createUser')
            ->assertHasErrors(['createRole']);

        $component->set('createRole', 'cajero')->call('createUser')->assertHasNoErrors();

        $created = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('cajero'));
    }

    public function test_delete_is_reversible_and_permanent_delete_is_not_exposed(): void
    {
        $owner = $this->owner();
        $employee = User::factory()->create();
        $employee->assignRole('mesero');
        $this->actingAs($owner);

        Livewire::test(UserList::class)->call('softDelete', $employee->id)->assertHasNoErrors();

        $this->assertSoftDeleted('users', ['id' => $employee->id]);
        $this->assertFalse(method_exists(UserList::class, 'forceDelete'));

        Livewire::test(UserList::class)->call('restore', $employee->id)->assertHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'deleted_at' => null]);
    }

    public function test_ban_closes_sessions_blocks_login_and_can_be_reversed(): void
    {
        $owner = $this->owner();
        $employee = User::factory()->create(['email' => 'blocked@example.test']);
        $employee->assignRole('cajero');
        DB::table('sessions')->insert([
            'id' => 'employee-session', 'user_id' => $employee->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test', 'payload' => 'test', 'last_activity' => now()->timestamp,
        ]);
        $this->actingAs($owner);

        Livewire::test(UserList::class)
            ->set('banUserId', $employee->id)
            ->set('banReason', 'Suspensión administrativa temporal')
            ->call('banUser')
            ->assertHasNoErrors();

        $employee->refresh();
        $this->assertTrue($employee->isBanned());
        $this->assertSame($owner->id, $employee->banned_by);
        $this->assertDatabaseMissing('sessions', ['id' => 'employee-session']);

        auth()->logout();
        Volt::test('pages.auth.login')
            ->set('form.email', 'blocked@example.test')
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors(['form.email']);
        $this->assertGuest();

        $this->actingAs($owner);
        Livewire::test(UserList::class)->call('unbanUser', $employee->id)->assertHasNoErrors();
        $this->assertNull($employee->fresh()->banned_at);

        auth()->logout();
        Volt::test('pages.auth.login')
            ->set('form.email', 'blocked@example.test')
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('app.dashboard', absolute: false));
        $this->assertAuthenticatedAs($employee);
    }

    public function test_banned_authenticated_user_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create(['banned_at' => now(), 'ban_reason' => 'Acceso suspendido']);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_view_only_user_cannot_ban_or_delete_accounts(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('ver usuarios');
        $target = User::factory()->create();

        Livewire::actingAs($viewer)->test(UserList::class)
            ->call('openBanPanel', $target->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(UserList::class)
            ->call('softDelete', $target->id)
            ->assertForbidden();
    }

    private function owner(): User
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        return $owner;
    }
}
