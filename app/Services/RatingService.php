<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Rating;
use App\Models\User;

class RatingService
{
    /**
     * Check if a user can rate an order.
     *
     * @return array{canRate: bool, reason?: string}
     */
    public static function canRate(Order $order, User $customer): array
    {
        // Order must be delivered (not pending, preparing, etc.)
        if (! in_array($order->status->value, ['delivered'])) {
            return ['canRate' => false, 'reason' => 'order_not_delivered'];
        }

        // Customer must be the order's customer
        if ($order->customer_id !== $customer->id) {
            return ['canRate' => false, 'reason' => 'not_order_customer'];
        }

        // Customer must not have already rated this order
        if (Rating::where('order_id', $order->id)->where('customer_id', $customer->id)->exists()) {
            return ['canRate' => false, 'reason' => 'already_rated'];
        }

        return ['canRate' => true];
    }

    /**
     * Submit a rating for an order.
     *
     * @return array{success: bool, rating?: Rating, errors?: array, message: string}
     */
    public static function submitRating(array $data, User $customer): array
    {
        // Extract and validate
        $validated = \Validator::make($data, [
            'order_id' => ['required', 'exists:orders,id'],
            'merchant_score' => ['required', 'integer', 'between:1,5'],
            'driver_score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated->fails()) {
            return [
                'success' => false,
                'errors' => $validated->errors()->toArray(),
                'message' => 'Validation failed',
            ];
        }

        $order = Order::find($data['order_id']);

        // Check authorization/eligibility
        $canRate = static::canRate($order, $customer);

        if (! $canRate['canRate']) {
            return [
                'success' => false,
                'errors' => [['message' => $canRate['reason']]],
                'message' => 'Cannot rate this order',
            ];
        }

        // Create the rating
        $rating = Rating::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'merchant_score' => $data['merchant_score'],
            'driver_score' => $data['driver_score'],
            'comment' => $data['comment'] ?? null,
        ]);

        return [
            'success' => true,
            'rating' => $rating,
            'message' => 'Rating submitted successfully',
        ];
    }
}
