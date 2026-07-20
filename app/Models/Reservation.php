<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'seat_id',
    'price_at_booking',
    'ticket_number'
])]
class Reservation extends Model
{
    protected function casts(): array
    {
        return [
            'price_at_booking' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }
}
