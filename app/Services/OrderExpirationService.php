<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SeatStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderExpirationService
{
    public function expire(): int
    {
        $orders = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->where('valid_until', '<', now())
            ->get();

        $expiredCount = 0;

        foreach ($orders as $order) {
            try {
                $this->expireOrder($order);
                $expiredCount++;
            } catch (Throwable $e) {
                Log::error('Failed to expire pending order', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $expiredCount;
    }

    private function expireOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('reservations.seat');

            $order->update(['status' => OrderStatus::EXPIRED]);

            foreach ($order->reservations as $reservation) {
                $reservation->seat->update(['status' => SeatStatus::FREE]);
            }
        });
    }
}
