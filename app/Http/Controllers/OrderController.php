<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Http\Requests\AttachPaymentProviderRequest;
use App\Http\Requests\IndexOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderCancellationService;
use App\Services\OrderPaymentService;
use App\Services\OrderService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(IndexOrderRequest $request, Event $event): AnonymousResourceCollection
    {
        $orders = $event->orders()
            ->where('user_id', $request->validated('user_id'))
            ->with('reservations')
            ->latest('id')
            ->paginate();

        return OrderResource::collection($orders);
    }

    public function store(
        StoreOrderRequest $request,
        Event $event,
        UserService $userService,
        OrderService $orderService): JsonResponse
    {
        if ($event->status !== EventStatus::PUBLISHED || now()->lt($event->active_since)) {
            return response()->json(['message' => 'Event is not available for booking.'], 403);
        }

        $dto = $request->toDto();

        $allowedSeats = $userService->getAllowedSeatsCountForUser($event, $dto->userId);

        if ($dto->totalRequestedSeats() > $allowedSeats) {
            return response()->json([
                'message' => 'User ticket limit exceeded for this event.',
                'allowed_seats' => $allowedSeats,
                'requested_seats' => $dto->totalRequestedSeats(),
            ], 422);
        }

        $order = $orderService->create($event, $dto);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Event $event, Order $order): OrderResource
    {
        return new OrderResource($order->load('reservations'));
    }

    public function attachPaymentProvider(
        AttachPaymentProviderRequest $request,
        Event $event,
        Order $order,
        OrderPaymentService $paymentService,
    ): OrderResource {
        $order = $paymentService->attachPaymentProvider(
            $order,
            $request->validated('payment_provider_id'),
        );

        return new OrderResource($order);
    }

    public function confirmPayment(
        string $paymentProviderId,
        OrderPaymentService $paymentService,
    ): OrderResource {
        $order = $paymentService->confirmPayment($paymentProviderId);

        return new OrderResource($order);
    }

    public function cancel(
        Event $event,
        Order $order,
        OrderCancellationService $cancellationService,
    ): OrderResource {
        $order = $cancellationService->cancel($order);

        return new OrderResource($order);
    }
}
