<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:' . $this->maxKilobytes(),
                'mimes:' . $this->allowedMimes(),
            ],
            'collection' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'string', 'max:200'],
            'mediable_type' => ['nullable', 'string', 'in:user,article'],
            'mediable_id' => ['nullable', 'integer'],
            'async' => ['boolean'],
        ];
    }

    protected function maxKilobytes(): int
    {
        $mime = $this->file('file')?->getMimeType() ?? '';

        if (str_starts_with($mime, 'video/')) {
            return (int) config('cloudinary.max_video_size') / 1024;
        }

        if (str_starts_with($mime, 'image/')) {
            return (int) config('cloudinary.max_image_size') / 1024;
        }

        return (int) config('cloudinary.max_document_size') / 1024;
    }

    protected function allowedMimes(): string
    {
        return implode(',', [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff',
            'mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'wmv',
            'mp3', 'wav', 'ogg', 'aac',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'odt', 'ods', 'odp',
            'zip', 'tar', 'gz',
        ]);
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The file exceeds the maximum allowed size.',
            'file.mimes' => 'This file type is not supported.',
        ];
    }
}
