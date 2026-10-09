<?php

namespace Tests\Feature\Notification;

use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\OutForDelivery;
use App\Services\Push\WebPushService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Minishlink\WebPush\MessageSentReport;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private function subscriptionPayload(string $endpoint = 'https://push.example/sub-1'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key',
            ],
            'contentEncoding' => 'aesgcm',
        ];
    }

    public function test_guest_cannot_subscribe(): void
    {
        $this->postJson('/push/subscribe', $this->subscriptionPayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_subscribe(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/push/subscribe', $this->subscriptionPayload());

        $response->assertCreated()
            ->assertJsonPath('status', 'subscribed')
            ->assertJsonStructure(['status', 'subscription_id']);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example/sub-1',
        ]);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('test-p256dh-key', $body);
        $this->assertStringNotContainsString('test-auth-key', $body);
        $this->assertStringNotContainsString('PRIVATE', $body);
    }

    public function test_subscribe_validates_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/push/subscribe', ['endpoint' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);

        $this->actingAs($user)
            ->postJson('/push/subscribe', [
                'endpoint' => 'http://insecure.example/sub',
                'keys' => ['p256dh' => 'k', 'auth' => 'a'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint']);
    }

    public function test_user_can_unsubscribe_own_endpoint_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example/mine',
            'public_key' => 'k1',
            'auth_token' => 'a1',
        ]);
        PushSubscription::create([
            'user_id' => $other->id,
            'endpoint' => 'https://push.example/theirs',
            'public_key' => 'k2',
            'auth_token' => 'a2',
        ]);

        $this->actingAs($user)
            ->postJson('/push/unsubscribe', ['endpoint' => 'https://push.example/mine'])
            ->assertOk()
            ->assertJsonPath('status', 'unsubscribed');

        $this->actingAs($user)
            ->postJson('/push/unsubscribe', ['endpoint' => 'https://push.example/theirs'])
            ->assertOk()
            ->assertJsonPath('status', 'not_found');

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example/mine']);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example/theirs']);
    }

    public function test_database_notification_still_fires_without_vapid_config(): void
    {
        Config::set('webpush.vapid_public_key', null);
        Config::set('webpush.vapid_private_key', null);
        Config::set('webpush.vapid_subject', null);

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'status' => 'pending',
        ]);

        $user->notify(new OutForDelivery($order));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => OutForDelivery::class,
        ]);
    }

    public function test_channel_sends_push_when_configured(): void
    {
        Config::set('webpush.vapid_public_key', 'test-public');
        Config::set('webpush.vapid_private_key', 'test-private');
        Config::set('webpush.vapid_subject', 'mailto:test@example.com');

        $user = User::factory()->create();
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example/sub-1',
            'public_key' => 'k',
            'auth_token' => 'a',
        ]);
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'status' => 'pending',
        ]);

        $service = $this->createMock(WebPushService::class);
        $service->expects($this->once())
            ->method('sendToUser')
            ->with(
                $this->equalTo($user),
                $this->stringContains('Out for delivery'),
                $this->stringContains((string) $order->id),
                $this->arrayHasKey('order_id')
            )
            ->willReturn(['sent' => 1, 'cleaned' => 0]);
        $this->app->instance(WebPushService::class, $service);

        $user->notify(new OutForDelivery($order));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => OutForDelivery::class,
        ]);
    }

    public function test_channel_degrades_safely_without_config(): void
    {
        Config::set('webpush.vapid_public_key', null);

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'status' => 'pending',
        ]);

        // The channel still delegates; the service itself no-ops and the
        // database row is written regardless of push availability.
        $user->notify(new OutForDelivery($order));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => OutForDelivery::class,
        ]);
    }

    public function test_service_is_noop_without_vapid_config(): void
    {
        Config::set('webpush.vapid_public_key', null);
        Config::set('webpush.vapid_private_key', null);
        Config::set('webpush.vapid_subject', null);

        $user = User::factory()->create();

        $result = app(WebPushService::class)->sendToUser($user, 'T', 'B');

        $this->assertSame(['sent' => 0, 'cleaned' => 0], $result);
        $this->assertFalse(app(WebPushService::class)->isConfigured());
    }

    public function test_expired_subscriptions_are_cleaned_up(): void
    {
        Config::set('webpush.vapid_public_key', 'test-public');
        Config::set('webpush.vapid_private_key', 'test-private');
        Config::set('webpush.vapid_subject', 'mailto:test@example.com');

        $user = User::factory()->create();
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example/expired',
            'public_key' => 'k',
            'auth_token' => 'a',
        ]);

        // Fake client answering 410 Gone with the library's real report
        // object: exercises the exact isSubscriptionExpired() branch with
        // zero network and zero crypto.
        $fakeClient = new class
        {
            public function sendOneNotification(object $subscription, ?string $payload = null): MessageSentReport
            {
                return new MessageSentReport(
                    new Request('POST', 'https://push.example/expired'),
                    new Response(410),
                    false,
                    'Gone'
                );
            }
        };

        $result = app(WebPushService::class)->deliver($user, 'T', 'B', [], fn () => $fakeClient);

        $this->assertSame(1, $result['cleaned']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, PushSubscription::where('user_id', $user->id)->count());
    }
}
