<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContactInquiryRequest;
use App\Http\Resources\Api\V1\ContactInquiryResource;
use App\Services\ContactInquiryService;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactInquiryService $contactInquiryService,
    ) {}

    public function store(StoreContactInquiryRequest $request)
    {
        try {
            $inquiry = $this->contactInquiryService->store(
                $request->validated(),
                $request,
            );
        } catch (QueryException $exception) {
            report($exception);

            return sendResponse(
                false,
                'Contact storage is not ready. Please run database migrations on the server.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return sendResponse(
                false,
                'Unable to send your message right now. Please try again later.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return sendResponse(
            true,
            'Your message has been sent. We\'ll get back to you soon.',
            new ContactInquiryResource($inquiry),
            HttpStatus::HTTP_CREATED,
        );
    }
}
