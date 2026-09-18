{{-- Split so Blade's compiled PHP doesn't end at the "?>". --}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ \Shazzoo\ContentStudio\ContentStudio::indexUrl() }}</loc>
        @if ($articles->isNotEmpty())
            <lastmod>{{ $articles->max('updated_at')->toAtomString() }}</lastmod>
        @endif
    </url>
    @foreach ($articles as $article)
        <url>
            <loc>{{ $article->url() }}</loc>
            <lastmod>{{ $article->updated_at->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
