<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCareersPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hero' => ['required', 'array'],
            'hero.badge' => ['required', 'string', 'max:80'],
            'hero.headline' => ['required', 'string', 'max:255'],
            'hero.subheadline' => ['required', 'string', 'max:2000'],
            'hero.primary_cta' => ['required', 'string', 'max:80'],
            'hero.secondary_cta' => ['required', 'string', 'max:80'],

            'stats' => ['required', 'array', 'max:8'],
            'stats.*.value' => ['required', 'string', 'max:40'],
            'stats.*.label' => ['required', 'string', 'max:80'],

            'perks_section' => ['required', 'array'],
            'perks_section.eyebrow' => ['required', 'string', 'max:80'],
            'perks_section.heading' => ['required', 'string', 'max:160'],

            'perks' => ['required', 'array', 'max:12'],
            'perks.*.icon' => ['nullable', 'string', 'max:2048'],
            'perks.*.emoji' => ['nullable', 'string', 'max:16'],
            'perks.*.title' => ['required', 'string', 'max:120'],
            'perks.*.description' => ['required', 'string', 'max:500'],

            'positions_section' => ['required', 'array'],
            'positions_section.eyebrow' => ['required', 'string', 'max:80'],
            'positions_section.heading' => ['required', 'string', 'max:160'],
            'positions_section.search_placeholder' => ['required', 'string', 'max:160'],

            'hiring_section' => ['required', 'array'],
            'hiring_section.eyebrow' => ['required', 'string', 'max:80'],
            'hiring_section.heading' => ['required', 'string', 'max:160'],

            'hiring_steps' => ['required', 'array', 'max:8'],
            'hiring_steps.*.number' => ['required', 'string', 'max:10'],
            'hiring_steps.*.title' => ['required', 'string', 'max:120'],
            'hiring_steps.*.description' => ['required', 'string', 'max:1000'],

            'testimonials_section' => ['required', 'array'],
            'testimonials_section.eyebrow' => ['required', 'string', 'max:80'],
            'testimonials_section.heading' => ['required', 'string', 'max:160'],

            'testimonials' => ['required', 'array', 'max:10'],
            'testimonials.*.quote' => ['required', 'string', 'max:2000'],
            'testimonials.*.initials' => ['required', 'string', 'max:8'],
            'testimonials.*.name' => ['required', 'string', 'max:120'],
            'testimonials.*.role' => ['required', 'string', 'max:120'],
            'testimonials.*.rating' => ['required', 'integer', 'min:1', 'max:5'],

            'faq_section' => ['required', 'array'],
            'faq_section.eyebrow' => ['required', 'string', 'max:80'],
            'faq_section.heading' => ['required', 'string', 'max:160'],

            'faqs' => ['required', 'array', 'max:20'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:5000'],

            'cta' => ['required', 'array'],
            'cta.heading' => ['required', 'string', 'max:160'],
            'cta.description' => ['required', 'string', 'max:1000'],
            'cta.button' => ['required', 'string', 'max:80'],
            'cta.button_url' => ['required', 'string', 'max:255'],
        ];
    }
}
