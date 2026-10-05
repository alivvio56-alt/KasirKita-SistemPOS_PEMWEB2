<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // in = restock (tambah), adjustment = set stok hasil stock opname
            'type' => ['required', 'in:in,adjustment'],
            'quantity' => ['required', 'integer', 'max:1000000', $this->input('type') === 'in' ? 'min:1' : 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['type' => 'jenis', 'quantity' => 'jumlah', 'note' => 'catatan'];
    }
}
