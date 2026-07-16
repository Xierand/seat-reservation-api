<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'active_since' => $this->active_since,
            'currency' => $this->currency,
            'event_date' => $this->event_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
