<?php

namespace App\Http\Requests;

use App\Enums\SeatStatus;
use App\Enums\SectorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row' => ['nullable', 'string', 'max:50'],
            'number' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(SeatStatus::class)],
            'base_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sector = $this->route('sector');

            if ($sector->type !== SectorType::SEATED) {
                return;
            }

            if (! $this->filled('row')) {
                $validator->errors()->add('row', 'Row is required for seated sectors.');
            }

            if (! $this->filled('number')) {
                $validator->errors()->add('number', 'Number is required for seated sectors.');
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
