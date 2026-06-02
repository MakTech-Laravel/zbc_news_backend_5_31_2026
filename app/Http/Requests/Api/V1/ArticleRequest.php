<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ArticleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ArticleRequest extends FormRequest
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
        $articleId = $this->route('article')?->id;

        return [
            'title'                 => ['required', 'string', 'max:255'],
            'slug'                  => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('articles', 'slug')->ignore($articleId),
            ],
            'seo_title'             => ['required', 'string', 'max:255'],
            'sub_title'             => ['nullable', 'string', 'max:255'],
            'article_description'   => ['required', 'string'],
            'excerpt'               => ['nullable', 'string'],
            'status'                => ['nullable', new Enum(ArticleStatus::class)],
            'featured_image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'sometimes'],
            'article_category_id'   => ['required', 'integer', 'exists:article_categories,id'],
            'scheduled_publishing' => [
                Rule::requiredIf(fn() => $this->input('status') === ArticleStatus::SCHEDULED->value),
                'nullable',
                'date_format:Y-m-d H:i:s',
                'after:now',
            ],
            'published_at'          => ['nullable', 'date'],
            'user_id'               => ['nullable', 'integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_publishing.required' => 'Scheduled publishing date is required when status is scheduled.',
            'scheduled_publishing.after'    => 'Scheduled publishing date must be a future date.',
        ];
    }
}
