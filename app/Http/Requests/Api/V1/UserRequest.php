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

            "avatar" => "nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048",

            "role" => "required|string|exists:roles,name",
        ];
    }
}
