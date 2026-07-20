<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Reservation;

class UserService
{
    public const int RESERVATION_LIMIT_PER_USER = 6;

    public function getAllowedSeatsCountForUser(Event $event, string $userId): int
    {
        $alreadyReserved = Reservation::query()
            ->whereHas('order', function ($query) use ($event, $userId) {
                $query->where('event_id', $event->id)
                    ->where('user_id', $userId)
                    ->whereIn('status', [
                        OrderStatus::PENDING,
                        OrderStatus::PAID
                    ]);
            })
            ->count();

        return max(0, self::RESERVATION_LIMIT_PER_USER - $alreadyReserved);
    }
}
