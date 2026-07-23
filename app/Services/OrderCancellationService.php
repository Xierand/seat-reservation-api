<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SeatStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderCancellationService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function cancel(Order $order): Order
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return $order->load('reservations');
        }

        return DB::transaction(function () use ($order) {
            $order->load('reservations.seat');

            $this->stateMachine->transition($order, OrderStatus::CANCELLED);

            foreach ($order->reservations as $reservation) {
                $reservation->seat->update(['status' => SeatStatus::FREE]);

                if ($reservation->ticket_number !== null) {
                    $reservation->update(['ticket_number' => null]);
                }
            }

            return $order->fresh(['reservations']);
        });
    }
}
