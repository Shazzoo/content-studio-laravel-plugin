<a class="cs-card group flex flex-col" href="{{ $article->url() }}">
    @if ($article->imageUrl())
        <img class="cs-featured-image aspect-video w-full rounded-xl object-cover transition group-hover:opacity-90" src="{{ $article->imageUrl() }}" alt="{{ $article->featured_image_alt }}" loading="lazy">
    @endif
    <p class="cs-meta mt-4 text-sm text-zinc-500">
        @if ($article->author_name)
            {{ $article->author_name }} &middot;
        @endif
        {{ __('content-studio::content_studio.reading_time', ['minutes' => $article->readTime()]) }}
        @if ($article->formattedDate())
            &middot; {{ $article->formattedDate() }}
        @endif
    </p>
    <h2 class="cs-card-title mt-2 text-xl font-semibold leading-snug group-hover:underline">{{ $article->title }}</h2>
    <p class="cs-card-excerpt mt-2 flex-1 text-zinc-600 dark:text-zinc-400">{{ $article->excerpt() }}</p>
    <span class="cs-link mt-4 text-sm font-semibold">{{ __('content-studio::content_studio.read_more') }} &rarr;</span>
</a>
