@if (\Shazzoo\ContentStudio\Support\Tracking::enabled())
    <div id="content-studio-tracking" hidden
        data-endpoint="{{ \Shazzoo\ContentStudio\Support\Tracking::endpoint() }}"
        data-project-key="{{ config('content-studio.project_code') }}"
        data-content-id="{{ $article->content_studio_article_id }}"
        data-article-slug="{{ $article->slug }}"></div>
    <script src="{{ \Shazzoo\ContentStudio\Support\Tracking::scriptUrl() }}" defer></script>
@endif
