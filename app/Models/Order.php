<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'event_id',
    'status',
    'total_amount',
    'valid_until',
    'currency',
    'payment_provider_id',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_amount' => 'decimal:2',
            'valid_until' => 'datetime',
            'currency' => Currency::class,
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
