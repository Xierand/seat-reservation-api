<?php

namespace App\Http\Requests;

use App\Enums\SeatLabelSequence;
use App\Enums\SectorType;
use App\Services\SeatGenerationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerateSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sequence = ['nullable', Rule::enum(SeatLabelSequence::class)];

        return [
            'base_price' => ['required', 'numeric', 'min:0'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:'.SeatGenerationService::MAX_SEATS_PER_REQUEST],
            'row' => ['sometimes', 'array'],
            'row.prefix' => $sequence,
            'row.name' => ['nullable', 'string', 'max:50'],
            'row.suffix' => $sequence,
            'row.count' => ['required_with:row', 'integer', 'min:1', 'max:'.SeatGenerationService::MAX_SEATS_PER_REQUEST],
            'number' => ['sometimes', 'array'],
            'number.prefix' => $sequence,
            'number.name' => ['nullable', 'string', 'max:50'],
            'number.suffix' => $sequence,
            'number.count' => ['required_with:number', 'integer', 'min:1', 'max:'.SeatGenerationService::MAX_SEATS_PER_REQUEST],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sector = $this->route('sector');
            $hasCapacity = $this->filled('capacity');
            $hasGrid = $this->has('row') || $this->has('number');

            if ($hasCapacity && $hasGrid) {
                $validator->errors()->add(
                    'capacity',
                    'Provide either capacity or row/number generation, not both.',
                );

                return;
            }

            if (! $hasCapacity && ! $hasGrid) {
                $validator->errors()->add(
                    'base_price',
                    'Provide capacity for standing seats or row/number for a seat grid.',
                );

                return;
            }

            if ($hasCapacity) {
                if (! in_array($sector->type, [SectorType::STANDING, SectorType::MIXED], true)) {
                    $validator->errors()->add(
                        'capacity',
                        'Capacity generation is only allowed for standing and mixed sectors.',
                    );
                }

                return;
            }

            if (! in_array($sector->type, [SectorType::SEATED, SectorType::MIXED], true)) {
                $validator->errors()->add(
                    'row',
                    'Grid generation is only allowed for seated and mixed sectors.',
                );

                return;
            }

            if (! $this->has('row') || ! $this->has('number')) {
                $validator->errors()->add(
                    'row',
                    'Both row and number configuration are required for grid generation.',
                );

                return;
            }

            $this->assertDimensionHasSequence($validator, 'row');
            $this->assertDimensionHasSequence($validator, 'number');

            $total = (int) $this->input('row.count') * (int) $this->input('number.count');

            if ($total > SeatGenerationService::MAX_SEATS_PER_REQUEST) {
                $validator->errors()->add(
                    'row.count',
                    'row.count × number.count must not exceed '.SeatGenerationService::MAX_SEATS_PER_REQUEST.'.',
                );
            }
        });
    }

    private function assertDimensionHasSequence(Validator $validator, string $dimension): void
    {
        $prefix = $this->input("{$dimension}.prefix");
        $suffix = $this->input("{$dimension}.suffix");

        if ($prefix === null && $suffix === null) {
            $validator->errors()->add(
                "{$dimension}.prefix",
                'At least one of prefix or suffix must be a sequence (ALPHABET, NUMBER, or ROMAN).',
            );
        }
    }
}
