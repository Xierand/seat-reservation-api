<?php

namespace App\DTOs;

readonly class StoreOrderData
{
    /**
     * @param  list<StoreOrderItemData>  $items
     */
    public function __construct(
        public string $userId,
        public array $items,
    ) {}

    public static function fromValidated(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            items: array_map(
                fn (array $item) => StoreOrderItemData::fromArray($item),
                $data['items'],
            ),
        );
    }

    public function totalRequestedSeats(): int
    {
        return array_reduce(
            $this->items,
            fn (int $total, StoreOrderItemData $item) => $total + $item->requestedSeatCount(),
            0,
        );
    }
}
