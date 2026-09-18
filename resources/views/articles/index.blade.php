@extends($layout)

@php
    $title = __('content-studio::content_studio.articles').' - '.config('app.name');
    if ($articles->currentPage() > 1) {
        $title .= ' - '.__('content-studio::content_studio.page', ['page' => $articles->currentPage()]);
    }
    $description = __('content-studio::content_studio.blog_description');
    $canonical = $articles->currentPage() > 1 ? $articles->url($articles->currentPage()) : \Shazzoo\ContentStudio\ContentStudio::indexUrl();
@endphp

@push('head')
    @include('content-studio::partials.seo', [
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'type' => 'website',
        'image' => null,
        'jsonLd' => [
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => $title,
            'description' => $description,
            'url' => $canonical,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $articles->values()->map(fn ($article, $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $article->url(),
                    'name' => $article->title,
                ])->all(),
            ],
        ],
    ])
@endpush

@section('content')
    <section class="cs-overview mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ __('content-studio::content_studio.articles') }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">{{ $description }}</p>

        @if ($articles->isEmpty())
            <p class="mt-10 text-zinc-500">{{ __('content-studio::content_studio.no_articles_available') }}</p>
        @else
            <div class="cs-grid mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    @if ($article->isHub())
                        @include('content-studio::partials.hub-card', ['article' => $article])
                    @else
                        @include('content-studio::partials.article-card', ['article' => $article])
                    @endif
                @endforeach
            </div>

            {{ $articles->links('content-studio::partials.pagination') }}
        @endif
    </section>
@endsection
