<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function place(User $customer, User $merchant, array $items, array $address, string $paymentMethod, ?string $idempotencyKey = null): Order
    {
        if ($merchant->role !== UserRole::Merchant || ! $merchant->isApproved()) {
            throw new InvalidArgumentException('Invalid merchant.');
        }

        $productIds = array_column($items, 'product_id');
        $profileId = $merchant->merchantProfile?->id;

        $products = Product::whereIn('id', $productIds)
            ->where('is_available', true)
            ->whereHas('category', fn ($q) => $q->where('merchant_id', $profileId))
            ->get()
            ->keyBy('id');

        if ($products->count() !== count(array_unique($productIds))) {
            throw new InvalidArgumentException('Some items are not available.');
        }

        $itemsTotal = 0;
        $orderItems = [];
        foreach ($items as $item) {
            $product = $products[$item['product_id']];
            $qty = max(1, (int) $item['quantity']);
            $unitPrice = (float) $product->price;
            $itemsTotal += $unitPrice * $qty;
            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
            ];
        }

        $deliveryFee = (float) config('delivery.delivery_fee', 15.00);
        $total = $itemsTotal + $deliveryFee;

        return DB::transaction(function () use ($customer, $merchant, $orderItems, $address, $paymentMethod, $itemsTotal, $deliveryFee, $total, $idempotencyKey) {
            $order = Order::create([
                'customer_id' => $customer->id,
                'merchant_id' => $merchant->id,
                'status' => OrderStatus::Pending->value,
                'items_total' => $itemsTotal,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'delivery_address' => $address,
                'payment_method' => $paymentMethod,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create(['order_id' => $order->id] + $item);
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $paymentMethod,
                'status' => $paymentMethod === 'cod'
                    ? PaymentStatus::PendingCod->value
                    : PaymentStatus::Pending->value,
            ]);

            event(new OrderStatusChanged($order, '', OrderStatus::Pending->value));

            AuditLog::record(auth()->user(), 'create_order', $order, [
                'items_total' => $itemsTotal,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
            ]);

            return $order;
        });
    }

    public function transition(Order $order, string $newStatus, ?string $reason = null, ?int $prepTimeMinutes = null): Order
    {
        $from = $order->status->value;

        if (! OrderStatus::canTransitionTo($from, $newStatus)) {
            throw new InvalidArgumentException("Cannot transition from {$from} to {$newStatus}.");
        }

        $order->status = OrderStatus::from($newStatus);

        if ($reason !== null) {
            $order->reject_reason = $reason;
        }
        if ($prepTimeMinutes !== null) {
            $order->prep_time_minutes = $prepTimeMinutes;
        }

        $order->save();

        event(new OrderStatusChanged($order, $from, $newStatus));

        AuditLog::record(
            auth()->user(),
            "order_{$newStatus}",
            $order,
            ['from' => $from, 'to' => $newStatus],
            $reason,
        );

        return $order;
    }

    public function accept(Order $order, int $prepTimeMinutes): Order
    {
        return $this->transition($order, OrderStatus::MerchantAccepted->value, null, $prepTimeMinutes);
    }

    public function reject(Order $order, string $reason): Order
    {
        return $this->transition($order, OrderStatus::Cancelled->value, $reason);
    }

    public function markReady(Order $order): Order
    {
        return $this->transition($order, OrderStatus::ReadyForPickup->value);
    }
}
