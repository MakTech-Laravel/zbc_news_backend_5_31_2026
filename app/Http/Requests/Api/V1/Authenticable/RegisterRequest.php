<?php

namespace App\Http\Requests\Api\V1\Authenticable;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function resolvedName(): string
    {
        if ($this->filled('name')) {
            return trim((string) $this->input('name'));
        }

        $first = trim((string) $this->input('first_name', ''));
        $last = trim((string) $this->input('last_name', ''));

        return trim("{$first} {$last}") ?: 'User';
    }
}
