<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
        <meta property="og:image:alt" content="{{ $imageAlt }}">
    @endif
    @if ($publishedAt)
        <meta property="article:published_time" content="{{ $publishedAt }}">
    @endif

    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if ($image)
        <meta name="twitter:image" content="{{ $image }}">
        <meta name="twitter:image:alt" content="{{ $imageAlt }}">
    @endif

    <meta http-equiv="refresh" content="0;url={{ $redirectUrl }}">
</head>
<body>
    <p>Redirecting to <a href="{{ $redirectUrl }}">{{ $title }}</a>…</p>
</body>
</html>
