<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Models\NavigationLink;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class NavigationLinkController extends Controller
{
    public function index(Request $request)
    {
        $location = $request->query('location');

        $query = NavigationLink::query()->orderBy('location')->orderBy('sort_order');
        if (is_string($location) && $location !== '') {
            $query->where('location', $location);
        }

        return sendResponse(true, 'Navigation links retrieved successfully', $query->get(), HttpStatus::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'location' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item = NavigationLink::query()->create($validated);
        return sendResponse(true, 'Navigation link created successfully', $item, HttpStatus::HTTP_CREATED);
    }

    public function update(Request $request, int $id)
    {
        $item = NavigationLink::query()->findOrFail($id);

        $validated = $request->validate([
            'location' => ['sometimes', 'string', 'max:50'],
            'label' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item->update($validated);
        return sendResponse(true, 'Navigation link updated successfully', $item, HttpStatus::HTTP_OK);
    }

    public function destroy(int $id)
    {
        $item = NavigationLink::query()->findOrFail($id);
        $item->delete();

        return sendResponse(true, 'Navigation link deleted successfully', null, HttpStatus::HTTP_OK);
    }
}

