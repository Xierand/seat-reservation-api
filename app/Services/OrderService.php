<?php

namespace App\Services;

use App\DTOs\StoreOrderData;
use App\DTOs\StoreOrderItemData;
use App\Enums\OrderStatus;
use App\Enums\SeatStatus;
use App\Exceptions\SeatsNotAvailableException;
use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    private const int ORDER_VALIDITY_MINUTES = 15;

    public function create(Event $event, StoreOrderData $dto): Order
    {
        return DB::transaction(function () use ($event, $dto) {
            $lockedSeats = $this->lockSeatsForItems($event, $dto);

            $this->assertSeatsAreFree($lockedSeats);

            $order = Order::query()->create([
                'user_id' => $dto->userId,
                'event_id' => $event->id,
                'status' => OrderStatus::PENDING,
                'total_amount' => 0,
                'valid_until' => now()->addMinutes(self::ORDER_VALIDITY_MINUTES),
                'currency' => $event->currency,
            ]);

            $totalAmount = '0.00';

            foreach ($lockedSeats as $seat) {
                Reservation::query()->create([
                    'order_id' => $order->id,
                    'seat_id' => $seat->id,
                    'price_at_booking' => $seat->base_price,
                    'ticket_number' => null,
                ]);

                $seat->update(['status' => SeatStatus::LOCKED]);

                $totalAmount = bcadd($totalAmount, (string) $seat->base_price, 2);
            }

            $order->update(['total_amount' => $totalAmount]);

            return $order->load('reservations');
        });
    }

    private function lockSeatsForItems(Event $event, StoreOrderData $dto): Collection
    {
        $seats = new Collection();

        foreach ($dto->items as $item) {
            $seats = $seats->merge(
                $item->isStanding()
                    ? $this->lockStandingSeats($event, $item)
                    : $this->lockSeatedSeats($event, $item)
            );
        }

        return $seats;
    }

    private function lockSeatedSeats(Event $event, StoreOrderItemData $item): Collection
    {
        $seatIds = collect($item->seatIds)->sort()->values()->all();

        $seats = $this->seatQuery($event, $item->sectorId)
            ->whereIn('id', $seatIds)
            ->lockForUpdate()
            ->get();

        if ($seats->count() !== count($seatIds)) {
            throw new SeatsNotAvailableException();
        }

        return $seats;
    }

    private function lockStandingSeats(Event $event, StoreOrderItemData $item): Collection
    {
        $seats = $this->seatQuery($event, $item->sectorId)
            ->where('status', SeatStatus::FREE)
            ->lock('for update skip locked')
            ->limit($item->quantity)
            ->get();

        if ($seats->count() !== $item->quantity) {
            throw new SeatsNotAvailableException();
        }

        return $seats;
    }

    private function seatQuery(Event $event, int $sectorId): Builder
    {
        return Seat::query()
            ->where('event_id', $event->id)
            ->where('sector_id', $sectorId)
            ->orderBy('id');
    }

    private function assertSeatsAreFree(Collection $seats): void
    {
        foreach ($seats as $seat) {
            if ($seat->status !== SeatStatus::FREE) {
                throw new SeatsNotAvailableException();
            }
        }
    }
}
