<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\Firebase\FirebaseAccessTokenProvider;
use App\Services\Firebase\FirebaseRealtimeDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class FirebaseRealtimeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_session_uses_livewire_fallback_when_realtime_is_disabled(): void
    {
        config()->set('firebase.realtime.enabled', false);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('app.notifications.realtime-session'))
            ->assertOk()
            ->assertExactJson([
                'enabled' => false,
                'fallback' => 'livewire',
            ]);
    }

    public function test_realtime_session_requires_authentication(): void
    {
        $this->getJson(route('app.notifications.realtime-session'))
            ->assertUnauthorized();
    }

    public function test_missing_firebase_credentials_never_interrupt_notification_flow(): void
    {
        config()->set('firebase.realtime.enabled', true);
        config()->set('firebase.realtime.database_url', 'https://example-default-rtdb.firebaseio.com');
        config()->set('firebase.realtime.credentials_path', storage_path('app/non-existent-firebase-key.json'));

        $notification = new AppNotification([
            'id' => (string) Str::uuid(),
            'notifiable_id' => 99,
            'event_key' => 'order.created',
            'created_at' => now(),
        ]);
        $notification->setAttribute($notification->getKeyName(), $notification->id);

        $this->assertFalse(app(FirebaseRealtimeDatabase::class)->publish($notification));
    }

    public function test_ready_realtime_database_receives_only_an_ephemeral_signal(): void
    {
        $credentialsPath = tempnam(sys_get_temp_dir(), 'firebase-test-');
        file_put_contents($credentialsPath, json_encode([
            'client_email' => 'firebase-test@example.test',
            'private_key' => 'unused-in-this-test',
        ], JSON_THROW_ON_ERROR));

        config()->set('firebase.realtime.enabled', true);
        config()->set('firebase.realtime.database_url', 'https://example-default-rtdb.firebaseio.com');
        config()->set('firebase.realtime.credentials_path', $credentialsPath);

        $tokens = Mockery::mock(FirebaseAccessTokenProvider::class);
        $tokens->shouldReceive('token')->once()->andReturn('test-access-token');
        $this->app->instance(FirebaseAccessTokenProvider::class, $tokens);
        Http::fake(['*' => Http::response(['ok' => true])]);

        $notification = new AppNotification([
            'id' => (string) Str::uuid(),
            'notifiable_id' => 42,
            'event_key' => 'order.ready',
            'data' => ['title' => 'Este contenido no debe viajar a Firebase'],
            'created_at' => now(),
        ]);
        $notification->setAttribute($notification->getKeyName(), $notification->id);

        try {
            $this->assertTrue(app(FirebaseRealtimeDatabase::class)->publish($notification));
            Http::assertSent(function ($request) use ($notification): bool {
                return $request->url() === 'https://example-default-rtdb.firebaseio.com/notifications/laravel_42/'.$notification->id.'.json'
                    && $request['id'] === $notification->id
                    && $request['event_key'] === 'order.ready'
                    && ! isset($request['data'], $request['title'], $request['message']);
            });
        } finally {
            @unlink($credentialsPath);
        }
    }

    public function test_cleanup_command_is_safe_when_realtime_is_disabled(): void
    {
        config()->set('firebase.realtime.enabled', false);

        $this->artisan('notifications:clear-realtime')
            ->assertSuccessful();
    }

    public function test_firebase_rules_only_allow_each_authenticated_user_to_read_their_branch(): void
    {
        $rules = json_decode(file_get_contents(base_path('firebase.database.rules.json')), true, flags: JSON_THROW_ON_ERROR);
        $userRules = $rules['rules']['notifications']['$uid'];

        $this->assertSame('auth != null && auth.uid === $uid', $userRules['.read']);
        $this->assertFalse($userRules['.write']);
        $this->assertFalse($rules['rules']['.read']);
        $this->assertFalse($rules['rules']['.write']);
    }
}
