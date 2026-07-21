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

/**
 * @return array{0: Order, 1: Seat}
 */
function createExpiredPendingOrderWithLockedSeat(): array
{
    $event = Event::factory()->create([
        'status' => EventStatus::PUBLISHED,
        'active_since' => now()->subHour(),
        'currency' => Currency::USD,
    ]);

    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $seat = Seat::factory()->locked()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
    ]);

    $order = Order::factory()->pending()->create([
        'event_id' => $event->id,
        'valid_until' => now()->subMinute(),
    ]);

    Reservation::factory()->create([
        'order_id' => $order->id,
        'seat_id' => $seat->id,
        'ticket_number' => null,
    ]);

    return [$order, $seat];
}

test('it expires pending orders past valid_until and releases seats', function () {
    [$order, $seat] = createExpiredPendingOrderWithLockedSeat();

    $this->artisan('orders:expire')
        ->assertSuccessful()
        ->expectsOutput('Expired 1 pending order(s).');

    expect($order->fresh()->status)->toBe(OrderStatus::EXPIRED);
    expect($seat->fresh()->status)->toBe(SeatStatus::FREE);

    $reservation = Reservation::query()->where('order_id', $order->id)->first();
    expect($reservation)->not->toBeNull();
    expect($reservation->ticket_number)->toBeNull();
});

test('it continues expiring other orders when one fails', function () {
    [$failingOrder] = createExpiredPendingOrderWithLockedSeat();
    [$successfulOrder, $successfulSeat] = createExpiredPendingOrderWithLockedSeat();

    Order::updating(function (Order $order) use ($failingOrder) {
        if ($order->id === $failingOrder->id) {
            throw new RuntimeException('Simulated database error');
        }
    });

    $this->artisan('orders:expire')->assertSuccessful();

    expect($failingOrder->fresh()->status)->toBe(OrderStatus::PENDING);
    expect($successfulOrder->fresh()->status)->toBe(OrderStatus::EXPIRED);
    expect($successfulSeat->fresh()->status)->toBe(SeatStatus::FREE);
});

test('it does not expire paid orders', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::PUBLISHED,
        'currency' => Currency::USD,
    ]);

    $order = Order::factory()->paid()->create([
        'event_id' => $event->id,
        'valid_until' => now()->subMinute(),
    ]);

    $this->artisan('orders:expire')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
});

test('it does not expire pending orders before valid_until', function () {
    [$order, $seat] = createExpiredPendingOrderWithLockedSeat();
    $order->update(['valid_until' => now()->addMinute()]);

    $this->artisan('orders:expire')
        ->assertSuccessful()
        ->expectsOutput('Expired 0 pending order(s).');

    expect($order->fresh()->status)->toBe(OrderStatus::PENDING);
    expect($seat->fresh()->status)->toBe(SeatStatus::LOCKED);
});
