<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class IndexOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'regex:/^(pending|processing|ready|completed|cancelled)(,(pending|processing|ready|completed|cancelled))*$/'],
            'type' => ['nullable', 'in:dine_in,take_away,preorder'],
            'payment_status' => ['nullable', 'in:unpaid,paid,refunded'],
            'customer_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
