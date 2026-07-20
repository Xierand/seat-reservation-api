<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'seat_id' => function (array $attributes) {
                $order = Order::findOrFail($attributes['order_id']);

                return Seat::factory()->create([
                    'event_id' => $order->event_id,
                ])->id;
            },
            'price_at_booking' => function (array $attributes) {
                return Seat::findOrFail($attributes['seat_id'])->base_price;
            },
            'ticket_number' => null,
        ];
    }

    public function withTicket(): static
    {
        return $this->state(fn () => [
            'ticket_number' => (string) Str::uuid(),
        ]);
    }
}
