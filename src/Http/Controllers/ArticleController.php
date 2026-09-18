<?php

namespace Shazzoo\ContentStudio\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Shazzoo\ContentStudio\ContentStudio;
use Shazzoo\ContentStudio\Models\Article;

class ArticleController
{
    public function index(): View
    {
        $locale = $this->locale();
        $perPage = max(1, min(48, (int) config('content-studio.articles_per_page')));

        $articles = Article::query()
            ->visible($locale)
            ->latest('generated_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('content-studio::articles.index', [
            'layout' => config('content-studio.layout'),
            'articles' => $articles,
        ]);
    }

    /**
     * The slug comes from the route parameter named "slug", so a site can
     * register the route with extra parameters of its own, such as a locale.
     */
    public function show(Request $request): View
    {
        $locale = $this->locale();
        $slug = (string) $request->route('slug');

        $article = Article::query()
            ->visible($locale)
            ->where('slug', $slug)
            ->with('relatedHubArticles')
            ->firstOrFail();

        $newer = Article::query()
            ->visible($locale)
            ->where(fn ($query) => $query
                ->where('generated_at', '>', $article->generated_at)
                ->orWhere(fn ($query) => $query->where('generated_at', $article->generated_at)->where('id', '>', $article->id)))
            ->orderBy('generated_at')
            ->orderBy('id')
            ->first();

        $older = Article::query()
            ->visible($locale)
            ->where(fn ($query) => $query
                ->where('generated_at', '<', $article->generated_at)
                ->orWhere(fn ($query) => $query->where('generated_at', $article->generated_at)->where('id', '<', $article->id)))
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();

        return view('content-studio::articles.show', [
            'layout' => config('content-studio.layout'),
            'article' => $article,
            'previousArticle' => $newer,
            'nextArticle' => $older,
        ]);
    }

    /**
     * The language of the blog, and of the translations on the page with it.
     */
    private function locale(): string
    {
        $locale = ContentStudio::locale();
        app()->setLocale($locale);

        return $locale;
    }
}
