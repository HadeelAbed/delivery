<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case MerchantAccepted = 'merchant_accepted';
    case Preparing = 'preparing';
    case ReadyForPickup = 'ready_for_pickup';
    case Assigned = 'assigned';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public static function transitionMap(): array
    {
        return [
            self::Pending->value => [self::MerchantAccepted->value, self::Cancelled->value],
            self::MerchantAccepted->value => [self::Preparing->value, self::ReadyForPickup->value, self::Cancelled->value],
            self::Preparing->value => [self::ReadyForPickup->value, self::Cancelled->value],
            self::ReadyForPickup->value => [self::Assigned->value, self::Cancelled->value],
            self::Assigned->value => [self::OutForDelivery->value, self::ReadyForPickup->value, self::Cancelled->value],
            self::OutForDelivery->value => [self::Delivered->value, self::Failed->value],
            self::Delivered->value => [],
            self::Failed->value => [],
            self::Cancelled->value => [],
        ];
    }

    public static function canTransitionTo(string $from, string $to): bool
    {
        return in_array($to, self::transitionMap()[$from] ?? [], true);
    }
}
