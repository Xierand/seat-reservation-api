<?php

namespace App\DTOs;

readonly class StoreOrderItemData
{
    /**
     * @param  list<int>  $seatIds
     */
    public function __construct(
        public int $sectorId,
        public array $seatIds = [],
        public ?int $quantity = null,
    ) {}

    public function isStanding(): bool
    {
        return $this->quantity !== null;
    }

    public function requestedSeatCount(): int
    {
        return $this->isStanding()
            ? $this->quantity
            : count($this->seatIds);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sectorId: (int) $data['sector_id'],
            seatIds: isset($data['seat_ids'])
                ? array_map('intval', $data['seat_ids'])
                : [],
            quantity: isset($data['quantity'])
                ? (int) $data['quantity']
                : null,
        );
    }
}
