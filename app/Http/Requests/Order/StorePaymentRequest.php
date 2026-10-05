<?php

namespace App\Http\Requests\Order;

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
            'payment_method' => ['required', 'in:cash,qris,transfer'],
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    public function attributes(): array
    {
        return ['payment_method' => 'metode pembayaran', 'paid_amount' => 'jumlah bayar'];
    }
}
