<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $event_date = $this->faker->dateTimeBetween('now', '+1 year');
        $active_since = $this->faker->dateTimeBetween('-1 year', $event_date);

        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->text(),
            'status' => $this->faker->randomElement(EventStatus::cases()),
            'active_since' => $active_since,
            'currency' => $this->faker->randomElement(Currency::cases()),
            'event_date' => $event_date,
        ];
    }
}
