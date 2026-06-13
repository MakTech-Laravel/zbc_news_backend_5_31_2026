<?php

return [
    'cloud_name'        => env('CLOUDINARY_CLOUD_NAME'),
    'api_key'           => env('CLOUDINARY_API_KEY'),
    'api_secret'        => env('CLOUDINARY_API_SECRET'),
    'url'               => env('CLOUDINARY_URL'),
    'secure'            => env('CLOUDINARY_SECURE', true),
    'cdn_subdomain'     => env('CLOUDINARY_CDN_SUBDOMAIN', false),

    'folder'            => env('CLOUDINARY_FOLDER', 'app'),
    'upload_preset'     => env('CLOUDINARY_UPLOAD_PRESET'),

    'max_image_size'    => (int) env('CLOUDINARY_MAX_IMAGE_SIZE', 10_485_760),
    'max_video_size'    => (int) env('CLOUDINARY_MAX_VIDEO_SIZE', 524_288_000),
    'max_document_size' => (int) env('CLOUDINARY_MAX_DOCUMENT_SIZE', 20_971_520),

    'transformations' => [
        'thumbnail' => ['width' => 200,  'height' => 200,  'crop' => 'fill', 'quality' => 'auto'],
        'avatar'    => ['width' => 120,  'height' => 120,  'crop' => 'fill', 'gravity' => 'face'],
        'banner'    => ['width' => 1200, 'height' => 400,  'crop' => 'fill', 'quality' => 'auto'],
    ],
];
