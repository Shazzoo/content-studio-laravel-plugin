{{-- The latest articles, for use on any page:
     <x-content-studio::latest-articles :limit="3" title="Uit de blog" /> --}}
@props(['limit' => 3, 'title' => null])

@php
    $articles = \Shazzoo\ContentStudio\Models\Article::query()
        ->visible(\Shazzoo\ContentStudio\ContentStudio::locale())
        ->latest('generated_at')
        ->limit($limit)
        ->get();
@endphp

@if ($articles->isNotEmpty())
    <section {{ $attributes->merge(['class' => 'cs-latest-articles']) }}>
        @if ($title)
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="text-3xl font-bold tracking-tight">{{ $title }}</h2>
                <a class="text-sm font-semibold hover:underline" href="{{ \Shazzoo\ContentStudio\ContentStudio::indexUrl() }}">{{ __('content-studio::content_studio.all_articles') }} &rarr;</a>
            </div>
        @endif

        <div class="cs-grid mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($articles as $article)
                @include('content-studio::partials.article-card', ['article' => $article])
            @endforeach
        </div>
    </section>
@endif
