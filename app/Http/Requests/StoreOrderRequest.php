<?php

namespace App\Http\Requests;

use App\DTOs\StoreOrderData;
use App\Enums\SectorType;
use App\Models\Seat;
use App\Models\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'items.*.seat_ids' => ['sometimes', 'array', 'min:1'],
            'items.*.seat_ids.*' => ['integer', 'distinct', 'exists:seats,id'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $event = $this->route('event');
            $items = $this->input('items', []);

            $allSeatIds = collect($items)
                ->pluck('seat_ids')
                ->filter()
                ->flatten()
                ->map(fn ($id) => (int) $id);

            if ($allSeatIds->count() !== $allSeatIds->unique()->count()) {
                $validator->errors()->add(
                    'items',
                    'Duplicate seat IDs are not allowed across items.',
                );
            }

            foreach ($items as $index => $item) {
                $sector = Sector::query()->find($item['sector_id'] ?? null);

                if ($sector === null) {
                    continue;
                }

                if ($sector->event_id !== $event->id) {
                    $validator->errors()->add(
                        "items.$index.sector_id",
                        'Sector does not belong to this event.',
                    );

                    continue;
                }

                $hasSeatIds = ! empty($item['seat_ids']);
                $hasQuantity = isset($item['quantity']);

                if ($sector->type === SectorType::SEATED) {
                    if (! $hasSeatIds) {
                        $validator->errors()->add(
                            "items.$index.seat_ids",
                            'Seat IDs are required for seated sectors.',
                        );
                    }

                    if ($hasQuantity) {
                        $validator->errors()->add(
                            "items.$index.quantity",
                            'Quantity is not allowed for seated sectors.',
                        );
                    }

                    if ($hasSeatIds) {
                        $this->assertSeatsBelongToSector(
                            $validator,
                            $event->id,
                            $sector->id,
                            $item['seat_ids'],
                            $index,
                        );
                    }

                    continue;
                }

                if ($sector->type === SectorType::MIXED) {
                    if ($hasSeatIds === $hasQuantity) {
                        $validator->errors()->add(
                            "items.$index",
                            'Mixed sectors require either seat_ids or quantity, but not both.',
                        );

                        continue;
                    }

                    if ($hasSeatIds) {
                        $this->assertSeatsBelongToSector(
                            $validator,
                            $event->id,
                            $sector->id,
                            $item['seat_ids'],
                            $index,
                        );
                    }

                    continue;
                }

                if ($sector->type === SectorType::STANDING) {
                    if (! $hasQuantity) {
                        $validator->errors()->add(
                            "items.$index.quantity",
                            'Quantity is required for standing sectors.',
                        );
                    }

                    if ($hasSeatIds) {
                        $validator->errors()->add(
                            "items.$index.seat_ids",
                            'Seat IDs are not allowed for standing sectors.',
                        );
                    }
                }
            }
        });
    }

    private function assertSeatsBelongToSector(
        Validator $validator,
        int $eventId,
        int $sectorId,
        array $seatIds,
        int $index,
    ): void {
        $validSeatCount = Seat::query()
            ->where('event_id', $eventId)
            ->where('sector_id', $sectorId)
            ->whereIn('id', $seatIds)
            ->count();

        if ($validSeatCount !== count($seatIds)) {
            $validator->errors()->add(
                "items.$index.seat_ids",
                'One or more seat IDs do not belong to the specified sector.',
            );
        }
    }

    public function toDto(): StoreOrderData
    {
        return StoreOrderData::fromValidated($this->validated());
    }
}
