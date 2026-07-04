<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use App\Models\Article;

class ArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            return $value . ':00';
        }

        return $value;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $articleId = null;
        $isUpdate  = $this->route('slug') !== null;

        if ($isUpdate) {
            $article   = Article::where('slug', $this->route('slug'))->first();
            $articleId = $article?->id;
        }

        $featuredImageRule = $this->hasFile('featured_image')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120']
            : ['nullable', 'string', 'max:2048'];

        $openGraphImageRule = $this->hasFile('open_graph_image')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120']
            : ['nullable', 'string', 'max:2048'];

        return [
            'title'                 => ['required', 'string', 'max:255'],
            // 'slug'  => [
            //     $isUpdate ? 'nullable' : 'required',
            //     'string',
            //     'max:255',
            //     Rule::unique('articles', 'slug')->ignore($articleId),
            // ],
            'slug' => [
                $isUpdate ? 'nullable' : 'required',
                'string',
                'max:255',
            ],
            'meta_title'             => ['nullable', 'string', 'max:255'],
            'meta_description'      => ['nullable', 'string'],
            'meta_keywords'         => ['nullable', 'string', 'max:500'],
            'sub_title'             => ['nullable', 'string', 'max:255'],
            'article_description'   => ['required', 'string'],
            'excerpt'               => ['nullable', 'string'],
            'status'                => ['nullable', new Enum(ArticleStatus::class)],
            'visibility'                => ['nullable', new Enum(ArticleVisibility::class)],
            'featured_image'        => $featuredImageRule,
            'open_graph_image'      => $openGraphImageRule,
            'article_category_id'   => ['required', 'integer', 'exists:article_categories,id'],
            'scheduled_publishing' => [
                Rule::requiredIf(fn() => $this->input('status') === ArticleStatus::SCHEDULED->value),
                'nullable',
                'date',
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
            'scheduled_publishing.required' => 'Scheduled publishing date and time are required when status is scheduled.',
            'scheduled_publishing.after'    => 'Scheduled publishing must be a future date and time.',
        ];
    }
}
