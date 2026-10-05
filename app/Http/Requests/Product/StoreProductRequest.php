<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sku' => 'SKU', 'category_id' => 'kategori', 'name' => 'nama produk', 'price' => 'harga',
            'stock' => 'stok', 'min_stock' => 'stok minimum', 'unit' => 'satuan',
        ];
    }
}
