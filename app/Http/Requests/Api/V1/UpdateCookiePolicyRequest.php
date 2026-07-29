<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCookiePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hero_meta' => ['required', 'string', 'max:500'],
            'hero_description' => ['required', 'string', 'max:5000'],
            'preferences_intro' => ['required', 'string', 'max:5000'],

            'categories' => ['required', 'array', 'size:4'],
            'categories.*.id' => ['required', 'string', Rule::in(['essential', 'analytics', 'preferences', 'advertising'])],
            'categories.*.title' => ['required', 'string', 'max:120'],
            'categories.*.description' => ['required', 'string', 'max:2000'],
            'categories.*.always_on' => ['required', 'boolean'],
            'categories.*.default_enabled' => ['required', 'boolean'],

            'browser_intro' => ['required', 'string', 'max:2000'],
            'browser_controls' => ['required', 'array', 'min:1', 'max:12'],
            'browser_controls.*.browser' => ['required', 'string', 'max:80'],
            'browser_controls.*.path' => ['required', 'string', 'max:255'],

            'faqs' => ['required', 'array', 'min:1', 'max:20'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:5000'],

            'contact_heading' => ['required', 'string', 'max:160'],
            'contact_description' => ['required', 'string', 'max:2000'],
            'contact_email' => ['required', 'email', 'max:255'],

            'banner_title' => ['required', 'string', 'max:160'],
            'banner_description' => ['required', 'string', 'max:2000'],
        ];
    }
}
