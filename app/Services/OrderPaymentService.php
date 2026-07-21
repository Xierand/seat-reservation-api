<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SeatStatus;
use App\Exceptions\OrderPaymentNotAllowedException;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPaymentService
{
    public function attachPaymentProvider(Order $order, string $paymentProviderId): Order
    {
        if ($order->status !== OrderStatus::PENDING) {
            throw new OrderPaymentNotAllowedException(
                'Payment provider can only be attached to pending orders.',
            );
        }

        if ($order->valid_until->isPast()) {
            throw new OrderPaymentNotAllowedException(
                'Cannot attach payment provider to an expired order.',
            );
        }

        if ($order->payment_provider_id !== null) {
            throw new OrderPaymentNotAllowedException(
                'Order already has a payment provider ID.',
            );
        }

        if (Order::query()->where('payment_provider_id', $paymentProviderId)->exists()) {
            throw new OrderPaymentNotAllowedException(
                'Payment provider ID is already in use.',
            );
        }

        $order->update(['payment_provider_id' => $paymentProviderId]);

        return $order->fresh();
    }

    public function confirmPayment(string $paymentProviderId): Order
    {
        $order = Order::query()
            ->where('payment_provider_id', $paymentProviderId)
            ->first();

        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        if ($order->status === OrderStatus::PAID) {
            return $order->load('reservations');
        }

        if ($order->status !== OrderStatus::PENDING) {
            throw new OrderPaymentNotAllowedException(
                "Cannot confirm payment for order with status '{$order->status->value}'.",
            );
        }

        if ($order->valid_until->isPast()) {
            throw new OrderPaymentNotAllowedException(
                'Cannot confirm payment for an expired order.',
            );
        }

        return DB::transaction(function () use ($order) {
            $order->load('reservations.seat');

            $order->update(['status' => OrderStatus::PAID]);

            foreach ($order->reservations as $reservation) {
                $reservation->update(['ticket_number' => (string) Str::uuid()]);
                $reservation->seat->update(['status' => SeatStatus::SOLD]);
            }

            return $order->fresh(['reservations']);
        });
    }
}
