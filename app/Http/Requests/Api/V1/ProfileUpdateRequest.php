<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        $profileImageRule = $this->hasFile('profile_image')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120']
            : ['nullable', 'string', 'max:2048'];

        return [
            'name' => 'required|string|max:255',
            'profile_image' => $profileImageRule,
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'region' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
        ];
    }
}
