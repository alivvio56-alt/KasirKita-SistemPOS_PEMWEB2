<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSelf = $this->user()?->id === $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            // admin tidak boleh menurunkan role / menonaktifkan dirinya sendiri
            'role' => ['sometimes', 'in:admin,kasir', $isSelf ? 'in:admin' : 'nullable'],
            'is_active' => ['sometimes', 'boolean', $isSelf ? 'accepted' : 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Role tidak valid (Anda tidak dapat menurunkan role akun sendiri).',
            'is_active.accepted' => 'Anda tidak dapat menonaktifkan akun sendiri.',
        ];
    }
}
