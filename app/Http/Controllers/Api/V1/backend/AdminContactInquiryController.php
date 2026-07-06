<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkContactInquiryRequest;
use App\Http\Requests\Api\V1\ReplyContactInquiryRequest;
use App\Http\Resources\Api\V1\ContactInquiryReplyResource;
use App\Http\Resources\Api\V1\ContactInquiryResource;
use App\Models\ContactInquiry;
use App\Services\ContactInquiryExportService;
use App\Services\ContactInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminContactInquiryController extends Controller
{
    public function __construct(
        private readonly ContactInquiryService $contactInquiryService,
        private readonly ContactInquiryExportService $exportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->contactInquiryService->adminList(
            $request->query('status'),
            $request->query('search'),
            max(1, min((int) $request->query('per_page', 15), 50)),
        );

        return sendResponse(
            true,
            'Contact messages retrieved successfully.',
            ContactInquiryResource::collection($paginator),
            HttpStatus::HTTP_OK,
            [
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        );
    }

    public function show(int $id): JsonResponse
    {
        $inquiry = $this->contactInquiryService->showAndMarkRead($id);

        return sendResponse(
            true,
            'Contact message retrieved successfully.',
            new ContactInquiryResource($inquiry->load(['replies.user'])),
            HttpStatus::HTTP_OK,
        );
    }

    public function markRead(int $id): JsonResponse
    {
        $inquiry = ContactInquiry::query()->findOrFail($id);
        $inquiry = $this->contactInquiryService->markRead($inquiry);

        return sendResponse(
            true,
            'Contact message marked as read.',
            new ContactInquiryResource($inquiry),
            HttpStatus::HTTP_OK,
        );
    }

    public function markUnread(int $id): JsonResponse
    {
        $inquiry = ContactInquiry::query()->findOrFail($id);

        try {
            $inquiry = $this->contactInquiryService->markUnread($inquiry);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return sendResponse(
            true,
            'Contact message marked as unread.',
            new ContactInquiryResource($inquiry),
            HttpStatus::HTTP_OK,
        );
    }

    public function markReplied(int $id): JsonResponse
    {
        $inquiry = ContactInquiry::query()->findOrFail($id);
        $inquiry = $this->contactInquiryService->markReplied($inquiry);

        return sendResponse(
            true,
            'Contact message marked as replied.',
            new ContactInquiryResource($inquiry),
            HttpStatus::HTTP_OK,
        );
    }

    public function archive(int $id): JsonResponse
    {
        $inquiry = ContactInquiry::query()->findOrFail($id);
        $inquiry = $this->contactInquiryService->archive($inquiry);

        return sendResponse(
            true,
            'Contact message archived.',
            new ContactInquiryResource($inquiry),
            HttpStatus::HTTP_OK,
        );
    }

    public function restore(int $id): JsonResponse
    {
        $inquiry = ContactInquiry::query()->findOrFail($id);

        try {
            $inquiry = $this->contactInquiryService->restore($inquiry);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return sendResponse(
            true,
            'Contact message restored.',
            new ContactInquiryResource($inquiry),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $inquiry = ContactInquiry::query()->findOrFail($id);
        $this->contactInquiryService->delete($inquiry);

        return sendResponse(
            true,
            'Contact message deleted successfully.',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function bulk(BulkContactInquiryRequest $request): JsonResponse
    {
        try {
            $count = $this->contactInquiryService->bulkAction(
                $request->validated('action'),
                $request->validated('ids'),
            );
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return sendResponse(
            true,
            "{$count} contact message(s) updated successfully.",
            ['count' => $count],
            HttpStatus::HTTP_OK,
        );
    }

    public function reply(ReplyContactInquiryRequest $request, int $id): JsonResponse
    {
        $inquiry = ContactInquiry::query()->findOrFail($id);
        $reply = $this->contactInquiryService->reply(
            $inquiry,
            $request->user(),
            $request->validated(),
        );

        return sendResponse(
            true,
            'Reply sent successfully.',
            new ContactInquiryReplyResource($reply),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function export(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $inquiries = $this->contactInquiryService->exportList(
            $request->query('status'),
            $request->query('search'),
        );

        return match ($format) {
            'csv' => $this->exportService->toCsv($inquiries),
            'excel', 'xls', 'xlsx' => $this->exportService->toExcel($inquiries),
            default => sendResponse(
                false,
                'Unsupported export format.',
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            ),
        };
    }
}
