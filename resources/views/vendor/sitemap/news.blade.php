{{-- Overrides spatie/laravel-sitemap's news view to emit children in the order
     the official Google News XSD requires: publication > publication_date >
     title (spatie ships title before publication_date, which fails strict
     schema validation even though Google itself is lenient). --}}
<news:news>
    <news:publication>
        <news:name>{{ $news->name }}</news:name>
        <news:language>{{ $news->language }}</news:language>
    </news:publication>
    <news:publication_date>{{ $news->publicationDate->toW3cString() }}</news:publication_date>
    <news:title>{{ $news->title }}</news:title>
@foreach($news->options as $tag => $value)
    <news:{{$tag}}>{{$value}}</news:{{$tag}}>
@endforeach
</news:news>
