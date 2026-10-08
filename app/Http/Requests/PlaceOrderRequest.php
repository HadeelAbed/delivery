<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'array'],
            'address.label' => ['required', 'string', 'max:255'],
            'address.lat' => ['required', 'numeric', 'between:-90,90'],
            'address.lng' => ['required', 'numeric', 'between:-180,180'],
            'payment_method' => ['required', 'string', 'in:cod,jawwal_pay,palpay'],
        ];
    }
}
