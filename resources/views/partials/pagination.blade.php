@if ($paginator->hasPages())
    <nav class="cs-pagination mt-12 flex flex-wrap gap-2" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="cs-page min-w-10 rounded-lg border border-zinc-200 px-3 py-2 text-center text-sm dark:border-zinc-800 text-zinc-400">&lsaquo;</span>
        @else
            <a class="cs-page min-w-10 rounded-lg border border-zinc-200 px-3 py-2 text-center text-sm dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-900" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo;</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="cs-page min-w-10 rounded-lg border border-zinc-200 px-3 py-2 text-center text-sm dark:border-zinc-800 text-zinc-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="cs-page min-w-10 rounded-lg border border-zinc-900 bg-zinc-900 px-3 py-2 text-center text-sm font-semibold text-white dark:border-white dark:bg-white dark:text-zinc-900" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="cs-page min-w-10 rounded-lg border border-zinc-200 px-3 py-2 text-center text-sm dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-900" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="cs-page min-w-10 rounded-lg border border-zinc-200 px-3 py-2 text-center text-sm dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-900" href="{{ $paginator->nextPageUrl() }}" rel="next">&rsaquo;</a>
        @else
            <span class="cs-page min-w-10 rounded-lg border border-zinc-200 px-3 py-2 text-center text-sm dark:border-zinc-800 text-zinc-400">&rsaquo;</span>
        @endif
    </nav>
@endif
