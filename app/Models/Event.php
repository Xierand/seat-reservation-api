<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'status',
    'active_since',
    'currency',
    'event_date'
])]
class Event extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'currency' => Currency::class,
            'active_since' => 'datetime',
            'event_date' => 'datetime',
        ];
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
