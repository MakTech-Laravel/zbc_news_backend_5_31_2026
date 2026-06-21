<?php

namespace App\Http\Requests\Api\v1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        $userId = $this->route('user') ?? $this->route('id');
        $isUpdate = !is_null($userId);

        return [
            "name" => "required|string|max:255",

            "email" => [
                "required",
                "email",
                "max:255",
                Rule::unique('users')->ignore($userId),
            ],

            "password" => $isUpdate
                ? "nullable|string|min:8"
                : "required|string|min:8",

            "role" => "required|string|exists:roles,name",

            "profile_image" => "nullable|string|max:2048",
            "bio"           => "nullable|string|max:1000",
            "region"        => "nullable|string|max:255",
        ];
    }
}
