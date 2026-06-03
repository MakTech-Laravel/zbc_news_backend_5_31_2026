<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SiteSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteSettingsRequest extends FormRequest
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
        $settings = SiteSettings::first();

        $timezoneRule = $settings ? 'nullable|integer' : 'required|integer';

        return [
            'site_name'                 => 'nullable|string|max:255',
            'site_tag'                  => 'nullable|string|max:255',
            'site_logo'                 => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'timezone'                  => $timezoneRule,
            'default_category_id'       => 'nullable|exists:article_categories,id',
            'posts_per_page'            => 'integer|min:1|max:100',
            'allow_comments'            => 'boolean',
            'authenticate_comment_only' => 'boolean',
            'related_article'           => 'integer|min:0',
            'pixeld_id'                 => 'nullable|integer',
            'g_messurment_id'           => 'nullable|integer',
            'g_api_secrete'             => 'nullable|string|max:255',
            'enable_comments'           => 'boolean',
        ];
    }
}
