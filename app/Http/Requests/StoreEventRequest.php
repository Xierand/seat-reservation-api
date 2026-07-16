<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'active_since' => ['required', 'date'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'event_date' => ['required', 'date', 'after:active_since'],
        ];
    }
}
