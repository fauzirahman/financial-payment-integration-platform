<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:100'],
            'event_type' => [
                'required',
                'string',
                Rule::in(['payment.succeeded', 'payment.failed']),
            ],
            'gateway' => ['required', 'string', 'max:50'],
            'gateway_transaction_id' => ['required', 'string', 'max:100'],
        ];
    }
}
