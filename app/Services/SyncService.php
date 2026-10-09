<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\SyncOutbox;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncService
{
    /**
     * Process a batch of offline actions from a client.
     *
     * @param  array<int, array{client_uuid: string, type: string, payload: array, at: string}>  $actions
     * @return array{ack: array, conflicts: array}
     */
    public function processBatch(User $user, array $actions): array
    {
        $ack = [];
        $conflicts = [];

        foreach ($actions as $action) {
            $uuid = $action['client_uuid'] ?? null;
            $type = $action['type'] ?? null;
            $payload = $action['payload'] ?? [];

            if (! $uuid || ! $type) {
                $conflicts[] = ['uuid' => $uuid, 'reason' => 'missing_uuid_or_type'];

                continue;
            }

            $existing = SyncOutbox::find($uuid);

            if ($existing && $existing->status === 'completed') {
                $ack[] = ['uuid' => $uuid, 'status' => 'already_processed'];

                continue;
            }

            $orderId = $payload['order_id'] ?? null;
            $order = $orderId ? Order::find($orderId) : null;

            if (! $order) {
                $conflicts[] = ['uuid' => $uuid, 'reason' => 'order_not_found'];

                continue;
            }

            $from = $order->status->value;

            if (! OrderStatus::canTransitionTo($from, $type)) {
                $conflicts[] = [
                    'uuid' => $uuid,
                    'reason' => 'invalid_transition',
                    'from' => $from,
                    'to' => $type,
                ];

                continue;
            }

            if (! $this->authorizeDeliveryAction($user, $order, $type)) {
                $conflicts[] = ['uuid' => $uuid, 'reason' => 'unauthorized'];

                continue;
            }

            try {
                DB::transaction(function () use ($user, $order, $type, $uuid, $payload, $action) {
                    app(OrderService::class)->transition($order, $type);

                    $this->mirrorDeliveryState($order, $type);

                    SyncOutbox::create([
                        'id' => $uuid,
                        'user_id' => $user->id,
                        'type' => $type,
                        'payload' => $payload,
                        'client_timestamp' => $action['at'] ?? null,
                        'status' => 'completed',
                    ]);
                });

                $ack[] = ['uuid' => $uuid, 'status' => 'processed'];
            } catch (\Exception $e) {
                $conflicts[] = ['uuid' => $uuid, 'reason' => $e->getMessage()];
            }
        }

        return ['ack' => $ack, 'conflicts' => $conflicts];
    }

    /**
     * Authorize an offline delivery action.
     *
     * Offline sync is a driver-only surface: assignment stays server-side
     * (AssignOrderJob), and delivery-lifecycle transitions require a delivery
     * assigned to the authenticated driver. Customers, merchants, unassigned
     * orders, and other drivers' deliveries are rejected as unauthorized without
     * mutating order or delivery state.
     */
    private function authorizeDeliveryAction(User $user, Order $order, string $type): bool
    {
        // Assignment is never performed through offline sync — it stays with AssignOrderJob.
        if ($type === OrderStatus::Assigned->value) {
            return false;
        }

        // Delivery-lifecycle transitions require an authenticated, assigned driver.
        if (in_array($type, ['out_for_delivery', 'delivered', 'failed'], true)) {
            if ($user->role !== UserRole::Driver) {
                return false;
            }

            return Delivery::query()
                ->where('order_id', $order->id)
                ->where('driver_id', $user->id)
                ->exists();
        }

        // Any other transition is not permitted through the offline sync surface.
        return false;
    }

    /**
     * Mirror the order transition onto the driver's existing deliveries row, matching
     * the web DeliveryController semantics (`pickup`/`deliver`). Never creates a row —
     * new assignments stay strictly with AssignOrderJob.
     */
    private function mirrorDeliveryState(Order $order, string $type): void
    {
        if (! in_array($type, ['out_for_delivery', 'delivered', 'failed'], true)) {
            return;
        }

        $delivery = Delivery::query()
            ->where('order_id', $order->id)
            ->first();

        if (! $delivery) {
            return;
        }

        $updates = ['status' => $type];

        if ($type === 'out_for_delivery') {
            $updates['picked_up_at'] = now();
        } elseif ($type === 'delivered') {
            $updates['delivered_at'] = now();
        }

        $delivery->update($updates);
    }
}
