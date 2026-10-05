<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Stok TIDAK dapat diubah lewat endpoint ini agar setiap perubahan stok
 * tercatat di riwayat (gunakan POST /products/{id}/stock-movements).
 */
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'sku' => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999'],
            'min_stock' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'stock' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['stock.prohibited' => 'Stok tidak dapat diubah langsung. Gunakan fitur restock / penyesuaian stok.'];
    }

    public function attributes(): array
    {
        return ['sku' => 'SKU', 'category_id' => 'kategori', 'name' => 'nama produk', 'price' => 'harga', 'min_stock' => 'stok minimum', 'unit' => 'satuan'];
    }
}
