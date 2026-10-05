<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
            'low_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:name,-name,price,-price,stock,-stock,created_at,-created_at'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
