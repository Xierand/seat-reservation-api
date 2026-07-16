<?php

namespace App\Models;

use App\Enums\SectorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'name',
    'description',
    'type',
])]
class Sector extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => SectorType::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
