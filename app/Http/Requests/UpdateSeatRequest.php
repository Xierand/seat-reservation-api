<?php

namespace App\Http\Requests;

use App\Enums\SeatStatus;
use App\Enums\SectorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sector = $this->route('sector');

            if (! in_array($sector->type, [SectorType::SEATED, SectorType::MIXED], true)) {
                return;
            }

            if (! $this->hasAny(['row', 'number'])) {
                return;
            }

            if (! $this->filled('row')) {
                $validator->errors()->add('row', 'Row is required for seated and mixed sectors.');
            }

            if (! $this->filled('number')) {
                $validator->errors()->add('number', 'Number is required for seated and mixed sectors.');
            }
        });
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
