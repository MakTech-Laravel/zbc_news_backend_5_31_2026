<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TagRequest;
use App\Http\Resources\Api\V1\TagResource;
use App\Services\TagService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class TagController extends Controller
{
        public function __construct(
        private readonly TagService $tagService
    ) {}
    public function index()
    {
        $tags = $this->tagService->getAllTags();

          return sendResponse(
            true,
            'Tags retrieved successfully',
            TagResource::collection($tags),
            HttpStatus::HTTP_OK,
        );

    }
    
    public function store(TagRequest $request)
    {
        $tag = $this->tagService->create($request->validated());
       
          return sendResponse(
            true,
            'Tag created successfully',
            new TagResource($tag),
            HttpStatus::HTTP_CREATED,
        );
    }
    
    public function show($id)
    {
        $tag = $this->tagService->getTagById($id);
        
        return sendResponse(
            true,
            'Tag retrieved successfully',
            new TagResource($tag),
            HttpStatus::HTTP_OK,
        );
    }
    
    public function update(TagRequest $request, $id)
    {
        $tag = $this->tagService->update($id, $request->validated());
        
        return sendResponse(
            true,
            'Tag updated successfully',
            new TagResource($tag),
            HttpStatus::HTTP_OK,
        );
    }
    
    public function destroy($id)
    {
        $this->tagService->delete($id);
        
        return sendResponse(
            true,
            'Tag deleted successfully',
            null,
            HttpStatus::HTTP_OK,
        );
    }
    
    public function restore(string $id)
    {
        $tag = $this->tagService->restore($id);

        return sendResponse(
            true,
            'Tag restored successfully',
            new TagResource($tag),
            HttpStatus::HTTP_OK,
        );
    }

    public function forceDelete(string $id)
    {
        $this->tagService->forceDelete($id);

        return sendResponse(
            true,
            'Tag permanently deleted',
            null,
            HttpStatus::HTTP_OK,
        );
    }
}
