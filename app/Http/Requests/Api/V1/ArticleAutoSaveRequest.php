<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ArticleVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ArticleAutoSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('scheduled_publishing')) {
            $merge['scheduled_publishing'] = $this->normalizeDatetimeInput(
                (string) $this->input('scheduled_publishing'),
            );
        }

        if ($this->filled('published_at')) {
            $merge['published_at'] = $this->normalizeDatetimeInput(
                (string) $this->input('published_at'),
            );
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    private function normalizeDatetimeInput(string $value): string
    {
        $value = trim(str_replace('T', ' ', $value));

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return $value.':00';
        }

        return $value;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $featuredImageRule = $this->hasFile('featured_image')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120']
            : ['nullable', 'string', 'max:2048'];

        $openGraphImageRule = $this->hasFile('open_graph_image')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120']
            : ['nullable', 'string', 'max:2048'];

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'article_description' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string'],
            'visibility' => ['nullable', new Enum(ArticleVisibility::class)],
            'featured_image' => $featuredImageRule,
            'open_graph_image' => $openGraphImageRule,
            'article_category_id' => ['nullable', 'integer', 'exists:article_categories,id'],
            'scheduled_publishing' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
