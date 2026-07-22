<?php

use App\Enums\Currency;
use App\Enums\EventStatus;
use App\Enums\SeatStatus;
use App\Enums\SectorType;
use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Seat;
use App\Models\Sector;
use Illuminate\Support\Facades\DB;

function createStandingSeatsForConcurrency(int $count, float $basePrice = 50.00): array
{
    $event = Event::factory()->create([
        'status' => EventStatus::PUBLISHED,
        'active_since' => now()->subHour(),
        'currency' => Currency::USD,
    ]);
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::STANDING,
    ]);
    $seats = Seat::factory()->count($count)->standing()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'base_price' => $basePrice,
    ]);

    return [$event, $sector, $seats];
}

test('it rejects standing reservation when seats are locked by a concurrent transaction via skip locked', function () {
    [$event, $sector, $seats] = createStandingSeatsForConcurrency(2);

    config(['database.connections.concurrent' => config('database.connections.'.config('database.default'))]);

    $concurrent = DB::connection('concurrent');
    $concurrent->beginTransaction();

    try {
        $concurrent->table('seats')
            ->whereIn('id', $seats->pluck('id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $this->postJson("/api/v1/events/{$event->id}/orders", [
            'user_id' => 'user-b',
            'items' => [
                [
                    'sector_id' => $sector->id,
                    'quantity' => 2,
                ],
            ],
        ])->assertStatus(409)
            ->assertJsonPath('error', 'seats_not_available');

        expect(Order::query()->count())->toBe(0);
        expect(Reservation::query()->count())->toBe(0);
        expect(Seat::query()->where('status', SeatStatus::FREE)->count())->toBe(2);
    } finally {
        $concurrent->rollBack();
        $concurrent->disconnect();
    }
});

test('it skips concurrently locked standing seats and books the remaining free ones', function () {
    [$event, $sector, $seats] = createStandingSeatsForConcurrency(2);
    $lockedSeatId = $seats->sortBy('id')->first()->id;
    $freeSeatId = $seats->sortBy('id')->last()->id;

    config(['database.connections.concurrent' => config('database.connections.'.config('database.default'))]);

    $concurrent = DB::connection('concurrent');
    $concurrent->beginTransaction();

    try {
        $concurrent->table('seats')
            ->where('id', $lockedSeatId)
            ->lockForUpdate()
            ->get();

        $this->postJson("/api/v1/events/{$event->id}/orders", [
            'user_id' => 'user-b',
            'items' => [
                [
                    'sector_id' => $sector->id,
                    'quantity' => 1,
                ],
            ],
        ])->assertCreated()
            ->assertJsonCount(1, 'data.reservations')
            ->assertJsonPath('data.reservations.0.seat_id', $freeSeatId);

        expect(Seat::query()->find($freeSeatId)->status)->toBe(SeatStatus::LOCKED);
        expect(Seat::query()->find($lockedSeatId)->status)->toBe(SeatStatus::FREE);
    } finally {
        $concurrent->rollBack();
        $concurrent->disconnect();
    }
});
