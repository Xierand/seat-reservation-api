<?php

use App\Enums\Currency;
use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\SeatStatus;
use App\Enums\SectorType;
use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Seat;
use App\Models\Sector;
use App\Services\UserService;
use Illuminate\Support\Collection;

function createPublishedEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'status' => EventStatus::PUBLISHED,
        'active_since' => now()->subHour(),
        'currency' => Currency::USD,
    ], $attributes));
}

/**
 * @return array{0: Event, 1: Sector, 2: Seat}
 */
function createSeatedSeatSetup(float $basePrice = 100.00): array
{
    $event = createPublishedEvent();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);
    $seat = Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => 'A',
        'number' => '1',
        'base_price' => $basePrice,
    ]);

    return [$event, $sector, $seat];
}

/**
 * @return array{0: Event, 1: Sector, 2: Collection<int, Seat>}
 */
function createStandingSeatsSetup(int $count, float $basePrice = 50.00): array
{
    $event = createPublishedEvent();
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

test('it creates an order for seated seats', function () {
    [$event, $sector, $seat] = createSeatedSeatSetup(100);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$seat->id],
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_amount', '100.00')
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonCount(1, 'data.reservations');

    $this->assertDatabaseHas('seats', [
        'id' => $seat->id,
        'status' => SeatStatus::LOCKED->value,
    ]);

    $this->assertDatabaseHas('reservations', [
        'seat_id' => $seat->id,
        'price_at_booking' => '100.00',
        'ticket_number' => null,
    ]);
});

test('it creates an order for standing seats by quantity', function () {
    [$event, $sector, $seats] = createStandingSeatsSetup(3, 50);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.total_amount', '100.00')
        ->assertJsonCount(2, 'data.reservations');

    expect(Seat::query()->where('status', SeatStatus::LOCKED)->count())->toBe(2);
    expect(Seat::query()->where('status', SeatStatus::FREE)->count())->toBe(1);
});

test('it creates an order with seated and standing items in one request', function () {
    $event = createPublishedEvent();
    $seatedSector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);
    $standingSector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::STANDING,
    ]);

    $seatA = Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $seatedSector->id,
        'row' => 'A',
        'number' => '1',
        'base_price' => 80,
    ]);
    Seat::factory()->count(2)->standing()->create([
        'event_id' => $event->id,
        'sector_id' => $standingSector->id,
        'base_price' => 30,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $seatedSector->id,
                'seat_ids' => [$seatA->id],
            ],
            [
                'sector_id' => $standingSector->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.total_amount', '140.00')
        ->assertJsonCount(3, 'data.reservations');
});

test('it rejects reservation when event is not published', function () {
    [$event, $sector, $seat] = createSeatedSeatSetup();
    $event->update(['status' => EventStatus::DRAFT]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$seat->id],
            ],
        ],
    ])->assertForbidden();
});

test('it rejects reservation when event is not yet active', function () {
    [$event, $sector, $seat] = createSeatedSeatSetup();
    $event->update(['active_since' => now()->addDay()]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$seat->id],
            ],
        ],
    ])->assertForbidden();
});

test('it rejects reservation when user exceeds ticket limit', function () {
    [$event, $sector, $seat] = createSeatedSeatSetup();

    $order = Order::factory()->create([
        'event_id' => $event->id,
        'user_id' => 'user-a',
        'status' => OrderStatus::PAID,
    ]);

    Reservation::factory()->count(UserService::RESERVATION_LIMIT_PER_USER)->create([
        'order_id' => $order->id,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$seat->id],
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonPath('allowed_seats', 0)
        ->assertJsonPath('requested_seats', 1);
});

test('it rejects reservation when seated seat is already locked', function () {
    [$event, $sector, $seat] = createSeatedSeatSetup();

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$seat->id],
            ],
        ],
    ])->assertCreated();

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-b',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$seat->id],
            ],
        ],
    ])->assertStatus(409)
        ->assertJsonPath('error', 'seats_not_available');

    expect(Order::query()->count())->toBe(1);
    expect(Reservation::query()->count())->toBe(1);
});

test('it rejects reservation when not enough standing seats are available', function () {
    [$event, $sector] = createStandingSeatsSetup(1);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'quantity' => 2,
            ],
        ],
    ])->assertStatus(409)
        ->assertJsonPath('error', 'seats_not_available');

    expect(Order::query()->count())->toBe(0);
    expect(Seat::query()->where('status', SeatStatus::LOCKED)->count())->toBe(0);
});

test('it rejects reservation when standing seats were taken by another order', function () {
    [$event, $sector] = createStandingSeatsSetup(2);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated();

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-b',
        'items' => [
            [
                'sector_id' => $sector->id,
                'quantity' => 1,
            ],
        ],
    ])->assertStatus(409);

    expect(Order::query()->count())->toBe(1);
    expect(Reservation::query()->count())->toBe(2);
});

test('it rolls back order when any seat in the cart is unavailable', function () {
    [$event, $sector, $availableSeat] = createSeatedSeatSetup();
    $unavailableSeat = Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => 'A',
        'number' => '2',
        'base_price' => 100,
        'status' => SeatStatus::SOLD,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$availableSeat->id, $unavailableSeat->id],
            ],
        ],
    ])->assertStatus(409);

    expect(Order::query()->count())->toBe(0);
    expect(Reservation::query()->count())->toBe(0);
    expect($availableSeat->fresh()->status)->toBe(SeatStatus::FREE);
});

test('it creates an order for mixed sector using seat_ids', function () {
    $event = createPublishedEvent();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);
    $seat = Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => 'A',
        'number' => '1',
        'base_price' => 120,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$seat->id],
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.total_amount', '120.00')
        ->assertJsonCount(1, 'data.reservations');

    expect($seat->fresh()->status)->toBe(SeatStatus::LOCKED);
});

test('it creates an order for mixed sector using standing quantity', function () {
    $event = createPublishedEvent();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);
    Seat::factory()->count(2)->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => null,
        'number' => null,
        'base_price' => 40,
    ]);
    Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => 'A',
        'number' => '1',
        'base_price' => 100,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.total_amount', '80.00')
        ->assertJsonCount(2, 'data.reservations');

    expect(Seat::query()->whereNull('row')->whereNull('number')->where('status', SeatStatus::LOCKED)->count())->toBe(2);
    expect(Seat::query()->where('row', 'A')->where('number', '1')->value('status'))->toBe(SeatStatus::FREE);
});

test('it creates an order with seated and standing items in the same mixed sector', function () {
    $event = createPublishedEvent();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);
    $labeledSeat = Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => 'B',
        'number' => '2',
        'base_price' => 90,
    ]);
    Seat::factory()->count(2)->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => null,
        'number' => null,
        'base_price' => 30,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$labeledSeat->id],
            ],
            [
                'sector_id' => $sector->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.total_amount', '150.00')
        ->assertJsonCount(3, 'data.reservations');
});

test('it rejects mixed sector items that provide both seat_ids and quantity', function () {
    $event = createPublishedEvent();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);
    $seat = Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => 'A',
        'number' => '1',
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
                'seat_ids' => [$seat->id],
                'quantity' => 1,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0']);
});

test('it rejects mixed sector items that provide neither seat_ids nor quantity', function () {
    $event = createPublishedEvent();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders", [
        'user_id' => 'user-a',
        'items' => [
            [
                'sector_id' => $sector->id,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0']);
});
