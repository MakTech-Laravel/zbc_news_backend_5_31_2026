<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Http\Resources\Api\V1\Author as AuthorResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AuthorController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function show(string $slug, Request $request): JsonResponse
    {
        $perPage = $request->query('per_page') ? (int) $request->query('per_page') : null;
        $page = (int) $request->query('page', 1);

        $result = $this->userService->getPublicAuthorBySlug($slug, $perPage, $page);

        $user = $result['user'];
        $user->setAttribute('published_articles_count', $result['published_count']);

        return sendResponse(
            true,
            'Author profile retrieved successfully',
            [
                'author' => new AuthorResource($user),
                'articles' => ArticleResource::collection($result['items']),
                'meta' => $result['meta'],
            ],
            HttpStatus::HTTP_OK,
        );
    }
}
