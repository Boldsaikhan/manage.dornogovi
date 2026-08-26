<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Push\EmployeePushNotifier;
use App\Services\Push\WebPushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_to_push(): void
    {
        config([
            'webpush.vapid.public_key' => 'BPtestPublicKeyxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'webpush.vapid.private_key' => 'testPrivateKeyxxxxxxxxxxxxxxxxxxxx',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-1',
                'keys' => [
                    'p256dh' => 'p256dh-key',
                    'auth' => 'auth-key',
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'public_key' => 'p256dh-key',
        ]);
    }

    public function test_employee_notifier_matches_users_by_name(): void
    {
        $target = User::factory()->create(['name' => 'Б.Болд']);
        User::factory()->create(['name' => 'Өөр Хүн']);

        $mock = Mockery::mock(WebPushNotifier::class);
        $mock->shouldReceive('sendToUsers')
            ->once()
            ->withArgs(function ($users, $payload) use ($target) {
                $ids = collect($users)->map(fn ($u) => is_object($u) ? $u->id : $u)->all();

                return in_array($target->id, $ids, true)
                    && ($payload['title'] ?? '') === 'Шинэ үүрэг даалгавар';
            })
            ->andReturnNull();

        $this->app->instance(WebPushNotifier::class, $mock);

        app(EmployeePushNotifier::class)->notifyNamed('Б.Болд', [
            'title' => 'Шинэ үүрэг даалгавар',
            'body' => 'Тест',
            'url' => '/uureg',
        ]);
    }

    public function test_leave_store_notifies_named_employee(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = User::factory()->create(['name' => 'Ц.Мөнхбат']);

        $mock = Mockery::mock(WebPushNotifier::class);
        $mock->shouldReceive('enabled')->andReturn(true);
        $mock->shouldReceive('sendToUsers')
            ->once()
            ->withArgs(function ($users) use ($employee) {
                return collect($users)->contains(fn ($u) => is_object($u) && $u->id === $employee->id);
            })
            ->andReturnNull();

        $this->app->instance(WebPushNotifier::class, $mock);

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'baiguullaga',
            'org_name' => 'Тест хэлтэс',
            'person_name' => 'Ц.Мөнхбат',
            'signer' => 'acting',
            'type' => array_key_first(\App\Models\Leave::TYPES),
            'start_date' => now()->toDateString(),
            'days' => 1,
            'status' => 'approved',
        ])->assertRedirect();
    }

    public function test_unsubscribe_removes_subscription(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/remove-me';

        PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'endpoint' => $endpoint,
            'public_key' => 'k',
            'auth_token' => 'a',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => $endpoint])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
    }
}
