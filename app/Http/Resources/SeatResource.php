<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'sector_id' => $this->sector_id,
            'row' => $this->row,
            'number' => $this->number,
            'status' => $this->status,
            'base_price' => $this->base_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
