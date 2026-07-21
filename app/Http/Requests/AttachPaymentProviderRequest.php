<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachPaymentProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_provider_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('orders', 'payment_provider_id'),
            ],
        ];
    }
}
