<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class NewsletterController extends Controller
{
    public function subscribers(Request $request)
    {
        $status = $request->query('status');

        $query = NewsletterSubscriber::query()->latest('id');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return sendResponse(true, 'Newsletter subscribers retrieved successfully', $query->paginate(20), HttpStatus::HTTP_OK);
    }

    public function campaigns()
    {
        $campaigns = NewsletterCampaign::query()->latest('id')->paginate(20);
        return sendResponse(true, 'Newsletter campaigns retrieved successfully', $campaigns, HttpStatus::HTTP_OK);
    }

    public function storeCampaign(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'content_html' => ['required', 'string'],
            'status' => ['nullable', 'in:draft,scheduled,sent'],
            'scheduled_at' => ['nullable', 'date'],
            'segments' => ['nullable', 'array'],
        ]);

        $campaign = NewsletterCampaign::query()->create($validated);
        return sendResponse(true, 'Newsletter campaign created successfully', $campaign, HttpStatus::HTTP_CREATED);
    }
}

