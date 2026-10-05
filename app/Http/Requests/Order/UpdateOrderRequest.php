<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'type' => ['sometimes', 'in:dine_in,take_away,preorder'],
            'pickup_at' => ['nullable', 'date', 'after:now'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['sometimes', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'pelanggan', 'type' => 'jenis pesanan', 'pickup_at' => 'jadwal ambil',
            'discount' => 'diskon', 'items.*.product_id' => 'produk', 'items.*.quantity' => 'jumlah',
        ];
    }
}
