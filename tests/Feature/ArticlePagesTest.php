<?php

use Illuminate\Support\Facades\Blade;
use Shazzoo\ContentStudio\Models\Article;

function article(array $attributes = []): Article
{
    static $id = 0;
    $id++;

    return Article::create(array_merge([
        'content_studio_article_id' => $id,
        'locale' => 'nl',
        'title' => "Artikel {$id}",
        'slug' => "artikel-{$id}",
        'body_html' => '<p>Tekst</p>',
        'generated_at' => now()->subDays(10 - $id),
    ], $attributes));
}

it('lists articles under the configured route with pagination', function () {
    article();
    article();
    article();
    article(['locale' => 'en', 'title' => 'English one']);

    $this->get('/nieuws')
        ->assertOk()
        ->assertSee('Artikel 3')
        ->assertSee('Artikel 2')
        ->assertDontSee('Artikel 1<')
        ->assertDontSee('English one')
        ->assertSee('<link rel="canonical" href="http://localhost/nieuws">', false);

    $this->get('/nieuws?page=2')->assertOk()->assertSee('Artikel 1');
    $this->get('/blog')->assertNotFound();
});

it('shows an article with its SEO tags and tracking', function () {
    $older = article();
    $article = article(['seo_title' => 'SEO titel', 'meta_description' => 'Beschrijving']);

    $this->get('/nieuws/'.$article->slug)
        ->assertOk()
        ->assertSee('<title>SEO titel</title>', false)
        ->assertSee('<meta name="description" content="Beschrijving">', false)
        ->assertSee('<p>Tekst</p>', false)
        ->assertSee($older->url())
        ->assertSee('data-project-key="PRJ"', false)
        ->assertSee('/vendor/content-studio/tracking.js', false);
});

it('returns 404 for an unknown slug', function () {
    $this->get('/nieuws/bestaat-niet')->assertNotFound();
});

it('shows the child articles on a hub', function () {
    $hub = article(['content_type' => 'hub', 'content_studio_article_id' => 100]);
    article(['hub_content_id' => '100', 'title' => 'Verdieping']);

    $this->get('/nieuws/'.$hub->slug)->assertOk()->assertSee('Verdieping');
});


it('lists the index and articles in the sitemap', function () {
    $article = article();
    article(['locale' => 'en', 'slug' => 'english-one']);

    $this->get('/nieuws/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>http://localhost/nieuws</loc>', false)
        ->assertSee('<loc>'.$article->url().'</loc>', false)
        ->assertDontSee('english-one');
});

it('renders the latest articles component', function () {
    article(['title' => 'Oudste']);
    article(['title' => 'Middelste']);
    article(['title' => 'Nieuwste']);

    $html = Blade::render('<x-content-studio::latest-articles :limit="2" title="Uit de blog" />');

    expect($html)
        ->toContain('Uit de blog', 'Nieuwste', 'Middelste', 'http://localhost/nieuws')
        ->not->toContain('Oudste');
});

it('renders nothing when there are no articles', function () {
    expect(trim(Blade::render('<x-content-studio::latest-articles />')))->toBe('');
});

it('points the tracking script at the published file', function () {
    $article = article();

    $this->get('/nieuws/'.$article->slug)
        ->assertSee('<script src="http://localhost/vendor/content-studio/tracking.js" defer></script>', false);

    config(['content-studio.tracking.script_url' => 'https://cdn.example.com/tracking.js']);

    $this->get('/nieuws/'.$article->slug)->assertSee('https://cdn.example.com/tracking.js', false);
});

it('leaves the tracking out when it is disabled', function () {
    config(['content-studio.tracking.enabled' => false]);

    $article = article();

    $this->get('/nieuws/'.$article->slug)
        ->assertOk()
        ->assertDontSee('tracking.js', false);
});
