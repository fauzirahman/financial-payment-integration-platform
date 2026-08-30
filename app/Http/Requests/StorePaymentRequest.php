<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_number' => [
                'required',
                'string',
                'max:40',
            ],

            'customer_id' => [
                'required',
                'uuid',
                'exists:customers,id',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],

            'currency' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],

            'method' => [
                'required',
                'string',
                'max:30',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}