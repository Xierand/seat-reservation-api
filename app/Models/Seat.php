<?php

namespace App\Models;

use App\Enums\SeatStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id',
    'sector_id',
    'row',
    'number',
    'status',
    'base_price'
])]
class Seat extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SeatStatus::class,
            'base_price' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
