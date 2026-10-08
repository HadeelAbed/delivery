<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStateMachineTest extends TestCase
{
    public function test_valid_forward_transitions(): void
    {
        $this->assertTrue(OrderStatus::canTransitionTo('pending', 'merchant_accepted'));
        $this->assertTrue(OrderStatus::canTransitionTo('merchant_accepted', 'preparing'));
        $this->assertTrue(OrderStatus::canTransitionTo('preparing', 'ready_for_pickup'));
        $this->assertTrue(OrderStatus::canTransitionTo('ready_for_pickup', 'assigned'));
        $this->assertTrue(OrderStatus::canTransitionTo('assigned', 'out_for_delivery'));
        $this->assertTrue(OrderStatus::canTransitionTo('out_for_delivery', 'delivered'));
    }

    public function test_valid_failure_transitions(): void
    {
        $this->assertTrue(OrderStatus::canTransitionTo('pending', 'cancelled'));
        $this->assertTrue(OrderStatus::canTransitionTo('merchant_accepted', 'cancelled'));
        $this->assertTrue(OrderStatus::canTransitionTo('preparing', 'cancelled'));
        $this->assertTrue(OrderStatus::canTransitionTo('ready_for_pickup', 'cancelled'));
        $this->assertTrue(OrderStatus::canTransitionTo('assigned', 'cancelled'));
        $this->assertTrue(OrderStatus::canTransitionTo('out_for_delivery', 'failed'));
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $this->assertFalse(OrderStatus::canTransitionTo('pending', 'delivered'));
        $this->assertFalse(OrderStatus::canTransitionTo('pending', 'preparing'));
        $this->assertFalse(OrderStatus::canTransitionTo('delivered', 'pending'));
        $this->assertFalse(OrderStatus::canTransitionTo('cancelled', 'pending'));
        $this->assertFalse(OrderStatus::canTransitionTo('failed', 'delivered'));
        $this->assertFalse(OrderStatus::canTransitionTo('out_for_delivery', 'merchant_accepted'));
    }

    public function test_terminal_states_have_no_outgoing_transitions(): void
    {
        $this->assertFalse(OrderStatus::canTransitionTo('delivered', 'cancelled'));
        $this->assertFalse(OrderStatus::canTransitionTo('cancelled', 'failed'));
        $this->assertFalse(OrderStatus::canTransitionTo('failed', 'out_for_delivery'));
    }
}
