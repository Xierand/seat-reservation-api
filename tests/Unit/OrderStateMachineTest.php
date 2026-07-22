<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class);

test('pending can transition to paid, expired and cancelled', function () {
    expect(OrderStatus::PENDING->canTransitionTo(OrderStatus::PAID))->toBeTrue();
    expect(OrderStatus::PENDING->canTransitionTo(OrderStatus::EXPIRED))->toBeTrue();
    expect(OrderStatus::PENDING->canTransitionTo(OrderStatus::CANCELLED))->toBeTrue();
    expect(OrderStatus::PENDING->canTransitionTo(OrderStatus::PENDING))->toBeFalse();
});

test('paid can transition to cancelled for refunds', function () {
    expect(OrderStatus::PAID->canTransitionTo(OrderStatus::CANCELLED))->toBeTrue();
    expect(OrderStatus::PAID->canTransitionTo(OrderStatus::PENDING))->toBeFalse();
    expect(OrderStatus::PAID->canTransitionTo(OrderStatus::EXPIRED))->toBeFalse();
    expect(OrderStatus::PAID->isTerminal())->toBeFalse();
});

test('expired and cancelled are terminal', function (OrderStatus $status) {
    expect($status->isTerminal())->toBeTrue();
    expect($status->allowedTransitions())->toBe([]);
    expect($status->canTransitionTo(OrderStatus::PENDING))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::PAID))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::EXPIRED))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::CANCELLED))->toBeFalse();
})->with([
    OrderStatus::EXPIRED,
    OrderStatus::CANCELLED,
]);

test('state machine transitions a pending order to paid', function () {
    $order = Order::factory()->pending()->create();

    $result = app(OrderStateMachine::class)->transition($order, OrderStatus::PAID);

    expect($result->status)->toBe(OrderStatus::PAID);
    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
});

test('state machine transitions a paid order to cancelled', function () {
    $order = Order::factory()->paid()->create();

    $result = app(OrderStateMachine::class)->transition($order, OrderStatus::CANCELLED);

    expect($result->status)->toBe(OrderStatus::CANCELLED);
    expect($order->fresh()->status)->toBe(OrderStatus::CANCELLED);
});

test('state machine rejects invalid transitions', function () {
    $order = Order::factory()->expired()->create();

    expect(fn () => app(OrderStateMachine::class)->transition($order, OrderStatus::PENDING))
        ->toThrow(InvalidOrderStatusTransitionException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::EXPIRED);
});
