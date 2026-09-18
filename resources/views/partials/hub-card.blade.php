<div class="cs-hub col-span-full grid overflow-hidden rounded-xl border border-zinc-200 md:grid-cols-2 dark:border-zinc-800">
    @if ($article->imageUrl())
        <img class="cs-featured-image aspect-video h-full w-full object-cover" src="{{ $article->imageUrl() }}" alt="{{ $article->featured_image_alt }}" loading="lazy">
    @endif
    <div class="flex flex-col justify-center p-6 sm:p-8">
        <p class="cs-meta text-sm font-medium text-zinc-500">{{ __('content-studio::content_studio.pillar_content') }}</p>
        <h2 class="mt-2 text-2xl font-bold leading-tight">{{ $article->title }}</h2>
        <p class="mt-3 text-zinc-600 dark:text-zinc-400">{{ $article->excerpt() }}</p>
        <p class="mt-3 text-sm text-zinc-500">{{ __('content-studio::content_studio.contains_deep_dive_articles', ['count' => $article->relatedHubArticles()->count()]) }}</p>
        <a class="cs-button mt-6 inline-flex w-fit rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200" href="{{ $article->url() }}">{{ __('content-studio::content_studio.view_full_guide') }}</a>
    </div>
</div>
