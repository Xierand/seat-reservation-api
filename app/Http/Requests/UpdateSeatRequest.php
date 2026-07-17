<?php

namespace App\Http\Requests;

use App\Enums\SeatStatus;
use App\Enums\SectorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row' => ['sometimes', 'nullable', 'string', 'max:50'],
            'number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(SeatStatus::class)],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sector = $this->route('sector');

        if ($sector?->type === SectorType::STANDING) {
            $this->merge([
                'row' => null,
                'number' => null,
            ]);
        }
    }
}
