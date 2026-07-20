<?php

namespace App\Services;

use App\DTOs\StoreOrderData;
use App\Models\Event;
use App\Models\Order;
use LogicException;

class OrderService
{
    public function create(Event $event, StoreOrderData $dto): Order
    {
        throw new LogicException('not implemented yet');
    }
}
