<?php

use Illuminate\Support\Facades\Route;
use Shazzoo\ContentStudio\ContentStudio;
use Shazzoo\ContentStudio\Http\Controllers\ArticleController;
use Shazzoo\ContentStudio\Models\Article;

function translated(string $locale, string $slug, string $title, int $contentId = 1): Article
{
    return Article::create([
        'content_studio_article_id' => $contentId,
        'locale' => $locale,
        'title' => $title,
        'slug' => $slug,
        'body_html' => '<p>Tekst</p>',
        'generated_at' => now(),
    ]);
}

/** A site that registers the blog inside its own locale group. */
function registerLocalizedRoutes(): void
{
    Route::middleware('web')->prefix('{locale}')->whereIn('locale', ['nl', 'en'])->group(function () {
        Route::get('blog', [ArticleController::class, 'index'])->name('site.blog.index');
        Route::get('blog/{slug}', [ArticleController::class, 'show'])->name('site.blog.show');
    });

    ContentStudio::resolveLocaleUsing(fn () => request()->route('locale') ?? 'nl');
    ContentStudio::resolveUrlUsing(fn (Article $article) => route('site.blog.show', [
        'locale' => $article->locale,
        'slug' => $article->slug,
    ]));
    ContentStudio::resolveIndexUrlUsing(fn (string $locale) => route('site.blog.index', ['locale' => $locale]));
}

it('serves the blog on the routes of the site, in the locale of the url', function () {
    registerLocalizedRoutes();

    translated('nl', 'nederlands-artikel', 'Nederlands artikel');
    translated('en', 'english-article', 'English article');

    $this->get('/nl/blog')->assertOk()->assertSee('Nederlands artikel')->assertDontSee('English article');
    $this->get('/en/blog')->assertOk()->assertSee('English article')->assertDontSee('Nederlands artikel');
    $this->get('/en/blog/english-article')->assertOk()->assertSee('English article');
    // A slug that only exists in the other language is not found.
    $this->get('/en/blog/nederlands-artikel')->assertNotFound();
});

it('builds every link with the resolver of the site', function () {
    registerLocalizedRoutes();

    $article = translated('en', 'english-article', 'English article');

    expect($article->url())->toBe('http://localhost/en/blog/english-article')
        ->and(ContentStudio::indexUrl('en'))->toBe('http://localhost/en/blog');

    $this->get('/en/blog')
        ->assertSee('http://localhost/en/blog/english-article')
        ->assertDontSee('http://localhost/nieuws/');
});

it('finds the translations of an article', function () {
    $dutch = translated('nl', 'nederlands-artikel', 'Nederlands artikel');
    translated('en', 'english-article', 'English article');
    translated('nl', 'ander-artikel', 'Ander artikel', contentId: 2);

    registerLocalizedRoutes();

    expect($dutch->translations->pluck('slug')->all())->toBe(['english-article'])
        ->and($dutch->translations->first()->url())->toBe('http://localhost/en/blog/english-article');
});

it('keeps the package routes and the project locale by default', function () {
    translated('nl', 'nederlands-artikel', 'Nederlands artikel');

    $this->get('/nieuws')->assertOk()->assertSee('http://localhost/nieuws/nederlands-artikel');
});
