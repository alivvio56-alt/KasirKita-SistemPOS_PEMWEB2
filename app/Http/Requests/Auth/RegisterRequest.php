<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'phone' => 'nomor HP'];
    }
}
