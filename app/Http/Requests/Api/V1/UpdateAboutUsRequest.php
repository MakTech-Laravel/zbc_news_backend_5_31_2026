<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAboutUsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hero_title' => ['required', 'string', 'max:255'],
            'hero_subtitle' => ['required', 'string', 'max:500'],
            'intro_html' => ['required', 'string', 'max:100000'],
            'values' => ['required', 'array', 'max:12'],
            'values.*.icon' => ['required', 'string', 'max:80'],
            'values.*.title' => ['required', 'string', 'max:120'],
            'values.*.description' => ['required', 'string', 'max:1000'],
            'leadership_subtitle' => ['required', 'string', 'max:500'],
            'leaders' => ['required', 'array', 'max:24'],
            'leaders.*.name' => ['required', 'string', 'max:120'],
            'leaders.*.role' => ['required', 'string', 'max:160'],
            'leaders.*.bio' => ['required', 'string', 'max:1000'],
            'leaders.*.initials' => ['required', 'string', 'max:8'],
            'journey' => ['required', 'array', 'max:30'],
            'journey.*.year' => ['required', 'string', 'max:20'],
            'journey.*.short_year' => ['required', 'string', 'max:10'],
            'journey.*.description' => ['required', 'string', 'max:1000'],
        ];
    }
}
