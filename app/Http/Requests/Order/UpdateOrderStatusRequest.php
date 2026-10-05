<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)->except([OrderStatus::Pending])],
            'reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required_if' => 'Alasan pembatalan wajib diisi.'];
    }

    public function targetStatus(): OrderStatus
    {
        return OrderStatus::from($this->validated('status'));
    }
}
