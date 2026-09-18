@extends($layout)

@php
    $title = $article->seo_title ?: $article->title;
    $description = (string) $article->meta_description;
    $canonical = $article->url();
    $image = $article->imageUrl() ? url($article->imageUrl()) : null;
@endphp

@push('head')
    @include('content-studio::partials.seo', [
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'type' => 'article',
        'image' => $image,
        'ogTitle' => $article->og_title,
        'ogDescription' => $article->og_description,
        'twitterTitle' => $article->twitter_title,
        'twitterDescription' => $article->twitter_description,
        'jsonLd' => array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $article->title,
            'description' => $description,
            'url' => $canonical,
            'datePublished' => $article->generated_at?->toIso8601String(),
            'dateModified' => $article->updated_at?->toIso8601String(),
            'author' => $article->author_name ? ['@type' => 'Person', 'name' => $article->author_name] : null,
            'publisher' => ['@type' => 'Organization', 'name' => config('app.name')],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'image' => $image,
        ]),
    ])
@endpush

@section('content')
    <article class="cs-article mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <nav class="cs-breadcrumbs flex gap-2 text-sm text-zinc-500">
            <a class="hover:text-zinc-900 hover:underline dark:hover:text-zinc-100" href="{{ \Shazzoo\ContentStudio\ContentStudio::indexUrl() }}">{{ __('content-studio::content_studio.articles') }}</a>
            <span>/</span>
            <span class="truncate">{{ $article->title }}</span>
        </nav>

        <header class="cs-article-header mt-6 mb-8">
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ $article->title }}</h1>
            @if ($article->isHub() && $article->excerpt)
                <p class="cs-hub-intro mt-6 border-l-4 border-zinc-300 pl-4 text-xl text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">{{ $article->excerpt }}</p>
            @endif
            <p class="cs-meta mt-4 text-sm text-zinc-500">
                @if ($article->author_name)
                    <span>{{ $article->author_name }}</span> &middot;
                @endif
                @if ($article->formattedDate())
                    <span>{{ $article->formattedDate() }}</span> &middot;
                @endif
                <span>{{ __('content-studio::content_studio.reading_time', ['minutes' => $article->readTime()]) }}</span>
            </p>
        </header>

        @if ($article->imageUrl())
            <img class="cs-featured-image mb-10 aspect-video w-full rounded-xl object-cover" src="{{ $article->imageUrl() }}" alt="{{ $article->featured_image_alt }}">
        @endif

        <div class="article-body text-lg leading-8 text-zinc-700 dark:text-zinc-300 [&_a]:underline [&_blockquote]:my-6 [&_blockquote]:border-l-4 [&_blockquote]:border-zinc-300 [&_blockquote]:pl-4 [&_blockquote]:italic [&_figcaption]:mt-2 [&_figcaption]:text-sm [&_figcaption]:text-zinc-500 [&_figcaption:empty]:hidden [&_figure]:my-8 [&_h2]:mt-12 [&_h2]:mb-4 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-zinc-900 dark:[&_h2]:text-zinc-100 [&_h3]:mt-8 [&_h3]:mb-3 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-zinc-900 dark:[&_h3]:text-zinc-100 [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-lg [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-5 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 [&_.pull-quote]:my-8 [&_.pull-quote]:border-l-4 [&_.pull-quote]:border-zinc-900 [&_.pull-quote]:pl-6 [&_.pull-quote]:text-2xl [&_.pull-quote]:font-medium [&_.pull-quote]:italic dark:[&_.pull-quote]:border-zinc-100">
            {!! $article->body_html !!}
        </div>

        @if ($article->isHub() && $article->relatedHubArticles->isNotEmpty())
            <section class="cs-cluster mt-16 border-t border-zinc-200 pt-10 dark:border-zinc-800">
                <h2 class="text-2xl font-bold">{{ __('content-studio::content_studio.cluster_overview') }}</h2>
                <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('content-studio::content_studio.dive_into_details') }}</p>
                <div class="cs-grid mt-8 grid gap-8 sm:grid-cols-2">
                    @foreach ($article->relatedHubArticles as $child)
                        @include('content-studio::partials.article-card', ['article' => $child])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($previousArticle || $nextArticle)
            <nav class="cs-adjacent mt-16 grid gap-6 border-t border-zinc-200 pt-8 sm:grid-cols-2 dark:border-zinc-800">
                <div>
                    @if ($previousArticle)
                        <p class="mb-1 text-sm text-zinc-500">{{ __('content-studio::content_studio.previous_article') }}</p>
                        <a class="font-semibold hover:underline" href="{{ $previousArticle->url() }}">{{ $previousArticle->title }}</a>
                    @endif
                </div>
                <div class="sm:text-right">
                    @if ($nextArticle)
                        <p class="mb-1 text-sm text-zinc-500">{{ __('content-studio::content_studio.next_article') }}</p>
                        <a class="font-semibold hover:underline" href="{{ $nextArticle->url() }}">{{ $nextArticle->title }}</a>
                    @endif
                </div>
            </nav>
        @endif

        @include('content-studio::partials.tracking', ['article' => $article])
    </article>
@endsection
