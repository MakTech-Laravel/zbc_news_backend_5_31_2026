<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Models\AdSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdSlotController extends Controller
{
    public function index()
    {
        $slots = AdSlot::query()->orderBy('slot_key')->get();
        return sendResponse(true, 'Ad slots retrieved successfully', $slots, HttpStatus::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'slot_key' => ['required', 'string', 'max:100', 'unique:ad_slots,slot_key'],
            'name' => ['required', 'string', 'max:255'],
            'placement' => ['nullable', 'string', 'max:100'],
            'provider' => ['required', 'in:google,manual'],
            'is_active' => ['nullable', 'boolean'],
            'google_ad_client' => ['nullable', 'string', 'max:100'],
            'google_ad_slot' => ['nullable', 'string', 'max:100'],
            'manual_image_url' => ['nullable', 'string', 'max:500'],
            'manual_click_url' => ['nullable', 'string', 'max:500'],
            'manual_html' => ['nullable', 'string'],
        ]);

        $slot = AdSlot::query()->create($validated);
        return sendResponse(true, 'Ad slot created successfully', $slot, HttpStatus::HTTP_CREATED);
    }

    public function update(Request $request, int $id)
    {
        $slot = AdSlot::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'placement' => ['nullable', 'string', 'max:100'],
            'provider' => ['sometimes', 'in:google,manual'],
            'is_active' => ['nullable', 'boolean'],
            'google_ad_client' => ['nullable', 'string', 'max:100'],
            'google_ad_slot' => ['nullable', 'string', 'max:100'],
            'manual_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'manual_image_url' => ['nullable', 'string', 'max:500'],
            'manual_click_url' => ['nullable', 'string', 'max:500'],
            'manual_html' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('manual_image')) {
            if ($slot->manual_image_url) {
                $oldUrlPath = parse_url($slot->manual_image_url, PHP_URL_PATH) ?? '';
                if (str_starts_with($oldUrlPath, '/storage/')) {
                    $oldPath = substr($oldUrlPath, strlen('/storage/'));
                    if (str_starts_with($oldPath, 'ad-slots/')) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            }

            $path = $request->file('manual_image')->store('ad-slots', 'public');
            $validated['manual_image_url'] = Storage::url($path);
        }

        unset($validated['manual_image']);

        $slot->update($validated);
        return sendResponse(true, 'Ad slot updated successfully', $slot, HttpStatus::HTTP_OK);
    }
}

