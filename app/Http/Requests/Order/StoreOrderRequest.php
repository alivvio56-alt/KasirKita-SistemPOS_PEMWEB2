<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id', 'required_if:type,preorder'],
            'type' => ['required', 'in:dine_in,take_away,preorder'],
            'pickup_at' => ['nullable', 'required_if:type,preorder', 'date', 'after:now'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required_if' => 'Preorder wajib mencantumkan pelanggan.',
            'pickup_at.required_if' => 'Preorder wajib memiliki jadwal pengambilan.',
            'pickup_at.after' => 'Jadwal pengambilan harus di masa mendatang.',
            'items.required' => 'Pesanan harus berisi minimal 1 item.',
            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh muncul dua kali, ubah jumlahnya saja.',
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
