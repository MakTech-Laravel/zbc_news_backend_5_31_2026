<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccessibilityStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hero_eyebrow' => ['required', 'string', 'max:80'],
            'hero_title' => ['required', 'string', 'max:255'],
            'hero_intro' => ['required', 'string', 'max:5000'],

            'badges' => ['required', 'array', 'max:8'],
            'badges.*.label' => ['required', 'string', 'max:120'],
            'badges.*.variant' => ['required', 'string', Rule::in(['success', 'info'])],

            'commitment_heading' => ['required', 'string', 'max:255'],
            'commitment_paragraphs' => ['required', 'array', 'min:1', 'max:10'],
            'commitment_paragraphs.*' => ['required', 'string', 'max:5000'],

            'commitment_stats' => ['required', 'array', 'max:8'],
            'commitment_stats.*.value' => ['required', 'string', 'max:40'],
            'commitment_stats.*.label' => ['required', 'string', 'max:80'],

            'features_heading' => ['required', 'string', 'max:160'],
            'features' => ['required', 'array', 'max:8'],
            'features.*.title' => ['required', 'string', 'max:120'],
            'features.*.icon' => ['required', 'string', 'max:80'],
            'features.*.items' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.items.*' => ['required', 'string', 'max:500'],

            'shortcuts_heading' => ['required', 'string', 'max:160'],
            'keyboard_shortcuts' => ['required', 'array', 'max:20'],
            'keyboard_shortcuts.*.key' => ['required', 'string', 'max:80'],
            'keyboard_shortcuts.*.action' => ['required', 'string', 'max:255'],

            'technologies_heading' => ['required', 'string', 'max:160'],
            'supported_technologies' => ['required', 'array', 'max:20'],
            'supported_technologies.*.name' => ['required', 'string', 'max:80'],
            'supported_technologies.*.platform' => ['required', 'string', 'max:80'],
            'supported_technologies.*.status' => ['required', 'string', Rule::in(['Supported', 'Partial'])],

            'known_limitations' => ['required', 'string', 'max:5000'],
            'report_heading' => ['required', 'string', 'max:160'],
            'report_intro' => ['required', 'string', 'max:2000'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:80'],
            'contact_address' => ['required', 'string', 'max:255'],
            'cta_text' => ['required', 'string', 'max:160'],
            'cta_button_label' => ['required', 'string', 'max:80'],
        ];
    }
}
