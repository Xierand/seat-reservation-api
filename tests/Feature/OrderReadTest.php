<?php

use App\Enums\Currency;
use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;

test('it shows an order with reservations', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::PUBLISHED,
        'currency' => Currency::USD,
    ]);

    $order = Order::factory()->pending()->create([
        'event_id' => $event->id,
        'user_id' => 'user-a',
    ]);

    Reservation::factory()->create([
        'order_id' => $order->id,
        'ticket_number' => null,
    ]);

    $this->getJson("/api/v1/events/{$event->id}/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.user_id', 'user-a')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonCount(1, 'data.reservations');
});

test('it returns 404 when order belongs to another event', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    $order = Order::factory()->create([
        'event_id' => $eventA->id,
    ]);

    $this->getJson("/api/v1/events/{$eventB->id}/orders/{$order->id}")
        ->assertNotFound();
});

test('it lists orders for a user within an event', function () {
    $event = Event::factory()->create([
        'currency' => Currency::USD,
    ]);

    $userOrder = Order::factory()->pending()->create([
        'event_id' => $event->id,
        'user_id' => 'user-a',
    ]);
    Reservation::factory()->create(['order_id' => $userOrder->id]);

    Order::factory()->paid()->create([
        'event_id' => $event->id,
        'user_id' => 'user-b',
    ]);

    $this->getJson("/api/v1/events/{$event->id}/orders?user_id=user-a")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $userOrder->id)
        ->assertJsonPath('data.0.user_id', 'user-a')
        ->assertJsonCount(1, 'data.0.reservations')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('it rejects listing orders without user_id', function () {
    $event = Event::factory()->create();

    $this->getJson("/api/v1/events/{$event->id}/orders")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

test('it lists only orders for the given event', function () {
    $eventA = Event::factory()->create(['currency' => Currency::USD]);
    $eventB = Event::factory()->create(['currency' => Currency::USD]);

    $orderA = Order::factory()->create([
        'event_id' => $eventA->id,
        'user_id' => 'user-a',
        'status' => OrderStatus::PENDING,
    ]);

    Order::factory()->create([
        'event_id' => $eventB->id,
        'user_id' => 'user-a',
        'status' => OrderStatus::PAID,
    ]);

    $this->getJson("/api/v1/events/{$eventA->id}/orders?user_id=user-a")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $orderA->id);
});
