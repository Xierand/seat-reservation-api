<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => (string) Str::uuid(),
            'event_id' => Event::factory(),
            'status' => OrderStatus::PENDING,
            'total_amount' => $this->faker->randomFloat(2, 20, 500),
            'valid_until' => now()->addMinutes(15),
            'currency' => function (array $attributes) {
                return Event::findOrFail($attributes['event_id'])->currency;
            },
            'payment_provider_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::PENDING,
            'payment_provider_id' => null,
            'valid_until' => now()->addMinutes(15),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::PAID,
            'payment_provider_id' => 'pay_'.$this->faker->uuid(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::EXPIRED,
            'valid_until' => now()->subMinute(),
            'payment_provider_id' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::CANCELLED,
            'payment_provider_id' => null,
        ]);
    }
}
