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
use Illuminate\Support\Str;

/**
 * array{0: Event, 1: Order, 2: Seat}
 */
function createCancellablePendingOrder(): array
{
    $event = Event::factory()->create([
        'status' => EventStatus::PUBLISHED,
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
    ]);

    Reservation::factory()->create([
        'order_id' => $order->id,
        'seat_id' => $seat->id,
        'ticket_number' => null,
    ]);

    return [$event, $order, $seat];
}

test('it cancels a pending order and releases locked seats', function () {
    [$event, $order, $seat] = createCancellablePendingOrder();

    $this->postJson("/api/v1/events/{$event->id}/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonCount(1, 'data.reservations');

    expect($order->fresh()->status)->toBe(OrderStatus::CANCELLED);
    expect($seat->fresh()->status)->toBe(SeatStatus::FREE);
    expect(Reservation::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('it cancels a paid order as refund and releases sold seats', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::PUBLISHED,
        'currency' => Currency::USD,
    ]);

    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $seat = Seat::factory()->sold()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
    ]);

    $order = Order::factory()->paid()->create([
        'event_id' => $event->id,
    ]);

    Reservation::factory()->create([
        'order_id' => $order->id,
        'seat_id' => $seat->id,
        'ticket_number' => (string) Str::uuid(),
    ]);

    $this->postJson("/api/v1/events/{$event->id}/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.reservations.0.ticket_number', null);

    expect($order->fresh()->status)->toBe(OrderStatus::CANCELLED);
    expect($seat->fresh()->status)->toBe(SeatStatus::FREE);
});

test('it is idempotent when cancelling an already cancelled order', function () {
    [$event, $order] = createCancellablePendingOrder();

    $this->postJson("/api/v1/events/{$event->id}/orders/{$order->id}/cancel")->assertOk();

    $this->postJson("/api/v1/events/{$event->id}/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('it rejects cancelling an expired order', function () {
    [$event, $order] = createCancellablePendingOrder();
    $order->update(['status' => OrderStatus::EXPIRED]);

    $this->postJson("/api/v1/events/{$event->id}/orders/{$order->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_order_status_transition')
        ->assertJsonPath('from', 'expired')
        ->assertJsonPath('to', 'cancelled');
});
