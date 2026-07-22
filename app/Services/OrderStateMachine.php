<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Models\Order;

class OrderStateMachine
{
    public function transition(Order $order, OrderStatus $to): Order
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidOrderStatusTransitionException($from, $to);
        }

        $order->update(['status' => $to]);

        return $order;
    }
}
