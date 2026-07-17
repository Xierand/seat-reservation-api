<?php

namespace Database\Factories;

use App\Enums\SeatStatus;
use App\Enums\SectorType;
use App\Models\Seat;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sector_id' => Sector::factory(),
            'event_id' => function (array $attributes) {
                return Sector::findOrFail($attributes['sector_id'])->event_id;
            },
            'row' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']),
            'number' => (string) $this->faker->numberBetween(1, 40),
            'status' => SeatStatus::FREE,
            'base_price' => $this->faker->randomFloat(2, 20, 500),
        ];
    }

    public function seated(): static
    {
        return $this->state(fn () => [
            'sector_id' => Sector::factory()->state(['type' => SectorType::SEATED]),
            'row' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']),
            'number' => (string) $this->faker->numberBetween(1, 40),
        ]);
    }

    public function standing(): static
    {
        return $this->state(fn () => [
            'sector_id' => Sector::factory()->state(['type' => SectorType::STANDING]),
            'row' => null,
            'number' => null,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => SeatStatus::LOCKED,
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn () => [
            'status' => SeatStatus::SOLD,
        ]);
    }
}
