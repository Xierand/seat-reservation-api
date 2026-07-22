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

function createPayablePendingOrder(?string $paymentProviderId = null): array
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
        'base_price' => 100,
    ]);

    $order = Order::factory()->pending()->create([
        'event_id' => $event->id,
        'payment_provider_id' => $paymentProviderId,
    ]);

    Reservation::factory()->create([
        'order_id' => $order->id,
        'seat_id' => $seat->id,
        'ticket_number' => null,
    ]);

    return [$event, $order, $seat];
}

test('it attaches payment provider id to a pending order', function () {
    [$event, $order] = createPayablePendingOrder();

    $this->patchJson("/api/v1/events/{$event->id}/orders/{$order->id}/payment-provider", [
        'payment_provider_id' => 'pay_stripe_abc123',
    ])->assertOk()
        ->assertJsonPath('data.payment_provider_id', 'pay_stripe_abc123');

    expect($order->fresh()->payment_provider_id)->toBe('pay_stripe_abc123');
});

test('it rejects attaching payment provider to an expired order', function () {
    [$event, $order] = createPayablePendingOrder();
    $order->update(['valid_until' => now()->subMinute()]);

    $this->patchJson("/api/v1/events/{$event->id}/orders/{$order->id}/payment-provider", [
        'payment_provider_id' => 'pay_stripe_abc123',
    ])->assertStatus(409)
        ->assertJsonPath('error', 'order_payment_not_allowed');
});

test('it rejects duplicate payment provider id', function () {
    createPayablePendingOrder('pay_existing');

    [$event, $order] = createPayablePendingOrder();

    $this->patchJson("/api/v1/events/{$event->id}/orders/{$order->id}/payment-provider", [
        'payment_provider_id' => 'pay_existing',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_provider_id']);
});

test('it confirms payment for a pending order', function () {
    [$event, $order, $seat] = createPayablePendingOrder('pay_stripe_abc123');

    $this->postJson('/api/v1/orders/pay_stripe_abc123/confirm-payment')
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.payment_provider_id', 'pay_stripe_abc123');

    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
    expect($seat->fresh()->status)->toBe(SeatStatus::SOLD);

    $reservation = Reservation::query()->where('order_id', $order->id)->first();
    expect($reservation->ticket_number)->not->toBeNull();
    expect(Str::isUuid($reservation->ticket_number))->toBeTrue();
});

test('it rejects payment confirmation for an expired order', function () {
    createPayablePendingOrder('pay_expired_order')[1]
        ->update(['valid_until' => now()->subMinute()]);

    $this->postJson('/api/v1/orders/pay_expired_order/confirm-payment')
        ->assertStatus(409)
        ->assertJsonPath('error', 'order_payment_not_allowed');
});

test('it is idempotent when confirming payment twice', function () {
    [$event, $order, $seat] = createPayablePendingOrder('pay_idempotent');

    $firstResponse = $this->postJson('/api/v1/orders/pay_idempotent/confirm-payment')
        ->assertOk();

    $ticketNumber = $firstResponse->json('data.reservations.0.ticket_number');

    $this->postJson('/api/v1/orders/pay_idempotent/confirm-payment')
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.reservations.0.ticket_number', $ticketNumber);

    expect(Reservation::query()->where('order_id', $order->id)->count())->toBe(1);
    expect($seat->fresh()->status)->toBe(SeatStatus::SOLD);
});

test('it returns 404 for unknown payment provider id', function () {
    $this->postJson('/api/v1/orders/pay_does_not_exist/confirm-payment')
        ->assertNotFound();
});

test('it rejects payment confirmation for already expired status', function () {
    createPayablePendingOrder('pay_already_expired')[1]
        ->update(['status' => OrderStatus::EXPIRED]);

    $this->postJson('/api/v1/orders/pay_already_expired/confirm-payment')
        ->assertStatus(409)
        ->assertJsonPath('error', 'order_payment_not_allowed');
});
