<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::enum(EventStatus::class)],
            'active_since' => ['sometimes', 'required', 'date'],
            'currency' => ['sometimes', 'required', Rule::enum(Currency::class)],
            'event_date' => ['sometimes', 'required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Event|null $event */
            $event = $this->route('event');
            $activeSince = $this->input('active_since', $event?->active_since);
            $eventDate = $this->input('event_date', $event?->event_date);
            if ($activeSince && $eventDate && $eventDate <= $activeSince) {
                $validator->errors()->add(
                    'event_date',
                    'Event date must be after ticket sale start.'
                );
            }
        });
    }
}
