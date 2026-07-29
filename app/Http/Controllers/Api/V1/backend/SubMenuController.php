<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Enums\SubMenuKey;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Http\Resources\Api\V1\SubMenuFeaturedArticleResource;
use App\Models\Article;
use App\Services\SubMenuService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SubMenuController extends Controller
{
    public function __construct(
        private readonly SubMenuService $subMenuService,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'section' => ['nullable', 'string', Rule::in(SubMenuKey::values())],
        ]);

        $sections = isset($validated['section'])
            ? [$validated['section']]
            : SubMenuKey::values();

        $data = [];
        foreach ($sections as $section) {
            $snapshot = $this->subMenuService->adminSnapshot($section);
            $items = $snapshot['merged']->values()->map(function ($article, int $index) {
                $payload = (new ArticleResource($article))->resolve();
                $payload['serial'] = $index + 1;

                return $payload;
            })->all();

            $data[$section] = [
                'settings' => $snapshot['settings'],
                'manual' => SubMenuFeaturedArticleResource::collection($snapshot['manual'])->resolve(),
                'algorithmic' => ArticleResource::collection($snapshot['algorithmic'])->resolve(),
                'items' => $items,
            ];
        }

        return sendResponse(true, 'Sub menu snapshots retrieved successfully.', $data, HttpStatus::HTTP_OK);
    }

    public function updateSettings(Request $request, string $section)
    {
        $this->validateSectionOrFail($section);

        $validated = $request->validate([
            'limit' => ['sometimes', 'integer'],
            'trending_window_hours' => ['sometimes', 'integer'],
            'most_read_default_period' => ['sometimes', 'string', Rule::in(['today', 'week', 'month', 'all'])],
            'pinned_slots' => ['sometimes', 'integer'],
            'is_enabled' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
        ]);

        $settings = $this->subMenuService->updateSettings($section, $validated);

        return sendResponse(true, 'Sub menu settings updated successfully.', $settings, HttpStatus::HTTP_OK);
    }

    public function storeManual(Request $request, string $section)
    {
        $this->validateSectionOrFail($section);

        $validated = $request->validate([
            'article_id' => ['required', 'integer', 'exists:articles,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $entry = $this->subMenuService->upsertManualEntry($section, $validated, $request->user()?->id);

        return sendResponse(
            true,
            'Sub menu manual entry saved successfully.',
            new SubMenuFeaturedArticleResource($entry),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function reorderManual(Request $request, string $section)
    {
        $this->validateSectionOrFail($section);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:sub_menu_featured_articles,id'],
        ]);

        $entries = $this->subMenuService->reorderManualEntries($section, $validated['ids']);

        return sendResponse(
            true,
            'Sub menu manual order updated successfully.',
            SubMenuFeaturedArticleResource::collection($entries),
            HttpStatus::HTTP_OK,
        );
    }

    public function removeManual(int $id)
    {
        $this->subMenuService->removeManualEntry($id);

        return sendResponse(true, 'Sub menu manual entry removed successfully.', null, HttpStatus::HTTP_OK);
    }

    public function startLive(int $articleId)
    {
        $article = Article::query()->where('status', 'published')->findOrFail($articleId);
        $updated = $this->subMenuService->startLiveCoverage($article);

        return sendResponse(true, 'Live coverage started.', new ArticleResource($updated), HttpStatus::HTTP_OK);
    }

    public function endLive(int $articleId)
    {
        $article = Article::query()->findOrFail($articleId);
        $updated = $this->subMenuService->endLiveCoverage($article);

        return sendResponse(true, 'Live coverage ended.', new ArticleResource($updated), HttpStatus::HTTP_OK);
    }

    private function validateSectionOrFail(string $section): void
    {
        if (! in_array($section, SubMenuKey::values(), true)) {
            abort(HttpStatus::HTTP_UNPROCESSABLE_ENTITY, 'Invalid sub menu section.');
        }
    }
}