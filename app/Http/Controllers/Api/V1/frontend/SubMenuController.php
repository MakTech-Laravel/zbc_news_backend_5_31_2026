<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Enums\SubMenuKey;
use App\Http\Controllers\Controller;
use App\Services\SubMenuService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SubMenuController extends Controller
{
    public function __construct(
        private readonly SubMenuService $subMenuService,
    ) {}

    public function index()
    {
        $payload = [];

        foreach (SubMenuKey::cases() as $section) {
            $payload[$section->value] = $this->subMenuService->publicSection($section);
        }

        return sendResponse(
            true,
            'Sub menu retrieved successfully',
            $payload,
            HttpStatus::HTTP_OK,
        );
    }

    public function show(string $section)
    {
        if (! in_array($section, SubMenuKey::values(), true)) {
            return sendResponse(false, 'Invalid sub menu section.', null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(
            true,
            'Sub menu retrieved successfully',
            $this->subMenuService->publicSection($section),
            HttpStatus::HTTP_OK,
        );
    }
}