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

        $urlRule = ['nullable', 'url', 'max:2048'];

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('users', 'slug')->ignore($userId),
            ],
            'profile_image' => $profileImageRule,
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'region' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'public_title' => 'nullable|string|max:255',
            'facebook' => $urlRule,
            'twitter' => $urlRule,
            'linkedin' => $urlRule,
            'instagram' => $urlRule,
            'youtube' => $urlRule,
            'website' => $urlRule,
            'social_links' => 'nullable|array',
            'social_links.facebook' => $urlRule,
            'social_links.twitter' => $urlRule,
            'social_links.linkedin' => $urlRule,
            'social_links.instagram' => $urlRule,
            'social_links.youtube' => $urlRule,
            'social_links.website' => $urlRule,
        ];
    }
}
