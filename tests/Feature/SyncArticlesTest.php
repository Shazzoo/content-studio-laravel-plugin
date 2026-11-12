<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Shazzoo\ContentStudio\Models\Article;

function engineItem(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'status' => 'approved',
        'locale' => 'nl_NL',
        'title' => 'Eerste artikel',
        'slug' => 'eerste-artikel',
        'body_html' => '<p>Hallo</p>',
        'featured_image_url' => 'https://engine.test/images/hero.jpg',
        'generated_at' => '2026-09-01 10:00:00',
    ], $overrides);
}

beforeEach(function () {
    Storage::fake('public');
});

it('syncs approved articles across pages', function () {
    Http::fake([
        'engine.content-studio.com/api/v1/projects/PRJ' => Http::response(['data' => ['primary_locale' => 'nl-nl', 'content_counts' => ['total' => 3]]]),
        'engine.content-studio.com/api/v1/projects/PRJ/contents?status=approved' => Http::response([
            'data' => [engineItem(), engineItem(['id' => 2, 'status' => 'draft'])],
            'links' => ['next' => 'projects/PRJ/contents?status=approved&page=2'],
        ]),
        'engine.content-studio.com/api/v1/projects/PRJ/contents?status=approved&page=2' => Http::response([
            'data' => [engineItem(['id' => 3, 'slug' => null, 'title' => 'Zonder slug'])],
            'links' => ['next' => null],
        ]),
        'engine.test/*' => Http::response('image-bytes'),
    ]);

    $this->artisan('content-studio:sync')->assertSuccessful();

    expect(Article::pluck('slug')->all())->toBe(['eerste-artikel', 'zonder-slug-3'])
        ->and(Article::first()->locale)->toBe('nl-nl')
        ->and(Article::first()->featured_image_url)->toBe('content_studio_images/1/hero.jpg');

    Storage::disk('public')->assertExists('content_studio_images/1/hero.jpg');
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('fails without credentials', function () {
    config(['content-studio.api_key' => null]);
    Http::fake();

    $this->artisan('content-studio:sync')->assertFailed();

    Http::assertNothingSent();
});

it('fails when the engine returns an error', function () {
    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => Http::response('nope', 500),
    ]);

    $this->artisan('content-studio:sync')->assertFailed();
});

it('confirms publication', function () {
    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents?status=approved' => Http::response(['data' => [engineItem(['featured_image_url' => null])], 'links' => ['next' => null]]),
        '*/confirm-published' => Http::response(['ok' => true]),
    ]);

    $this->artisan('content-studio:sync')->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/projects/PRJ/contents/1/confirm-published')
        && $request['published_url'] === 'http://localhost/nieuws/eerste-artikel');
    expect(Article::first()->published_confirmed_at)->not->toBeNull();
});

it('leaves an existing article untouched when a download fails', function () {
    $item = engineItem();
    $imageResponse = fn () => Http::response('image-bytes');

    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => function () use (&$item) {
            return Http::response(['data' => [$item], 'links' => ['next' => null]]);
        },
        'engine.test/*' => function () use (&$imageResponse) {
            return $imageResponse();
        },
    ]);

    $this->artisan('content-studio:sync')->assertSuccessful();
    expect(Article::first()->featured_image_url)->toBe('content_studio_images/1/hero.jpg');

    // The Engine sends a new image, but downloading it fails.
    Storage::disk('public')->delete('content_studio_images/1/hero.jpg');
    $item = engineItem(['title' => 'Nieuwe titel', 'featured_image_url' => 'https://engine.test/images/nieuw.jpg']);
    $imageResponse = fn () => Http::response('nope', 500);

    $this->artisan('content-studio:sync')->assertFailed();

    // Nothing of the new version is stored, so the page keeps the old one.
    expect(Article::first())
        ->featured_image_url->toBe('content_studio_images/1/hero.jpg')
        ->title->toBe('Eerste artikel');
});

it('does not store a new article when the image connection times out', function () {
    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => Http::response(['data' => [engineItem()], 'links' => ['next' => null]]),
        'engine.test/*' => fn () => throw new ConnectionException('timeout'),
    ]);

    $this->artisan('content-studio:sync')->assertFailed();

    expect(Article::count())->toBe(0);
});

it('does not store or confirm an article with a failing image, and retries next sync', function () {
    $imageResponse = fn () => Http::response('nope', 500);

    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => Http::response(['data' => [engineItem()], 'links' => ['next' => null]]),
        'engine.test/*' => function () use (&$imageResponse) {
            return $imageResponse();
        },
    ]);

    $this->artisan('content-studio:sync')->assertFailed();

    // Not stored and not confirmed, so the Engine keeps offering the article
    // as approved and the site shows nothing half-finished.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'confirm-published'));
    expect(Article::count())->toBe(0);

    // The next sync downloads the image again and finishes the article.
    $imageResponse = fn () => Http::response('image-bytes');

    $this->artisan('content-studio:sync')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'confirm-published'));
    expect(Article::first())
        ->featured_image_url->toBe('content_studio_images/1/hero.jpg')
        ->published_confirmed_at->not->toBeNull();
});

it('empties the image when the engine no longer sends one', function () {
    $item = engineItem();

    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => function () use (&$item) {
            return Http::response(['data' => [$item], 'links' => ['next' => null]]);
        },
        'engine.test/*' => Http::response('image-bytes'),
    ]);

    $this->artisan('content-studio:sync')->assertSuccessful();
    expect(Article::first()->featured_image_url)->not->toBeNull();

    $item = engineItem(['featured_image_url' => null]);

    $this->artisan('content-studio:sync')->assertSuccessful();

    expect(Article::first()->featured_image_url)->toBeNull();
});

it('confirms publication by default', function () {

    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => Http::response(['data' => [engineItem(['featured_image_url' => null])], 'links' => ['next' => null]]),
        '*/confirm-published' => Http::response(['ok' => true]),
    ]);

    $this->artisan('content-studio:sync')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'confirm-published'));
});

it('does not confirm publication when the config turns it off', function () {
    config(['content-studio.confirm_published' => false]);

    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => Http::response(['data' => [engineItem(['featured_image_url' => null])], 'links' => ['next' => null]]),
    ]);

    $this->artisan('content-studio:sync')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'confirm-published'));
    expect(Article::first()->published_confirmed_at)->toBeNull();
});

it('stores the images on the configured disk and links to it', function () {
    config(['content-studio.disk' => 'cdn']);
    Storage::fake('cdn', ['url' => 'https://cdn.test']);

    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => Http::response(['data' => [engineItemWithDiagram()], 'links' => ['next' => null]]),
        '*/confirm-published' => Http::response(['ok' => true]),
        'engine.test/*' => Http::response('image-bytes'),
    ]);

    $this->artisan('content-studio:sync')->assertSuccessful();

    $article = Article::first();

    Storage::disk('cdn')->assertExists('content_studio_images/1/hero.jpg');
    Storage::disk('public')->assertMissing('content_studio_images/1/hero.jpg');
    expect($article->imageUrl())->toBe('https://cdn.test/content_studio_images/1/hero.jpg')
        ->and($article->body_html)->toContain('src="https://cdn.test/content_studio_images/1/diagrams/');
});

it('moves the images and the links in the text over when the disk changes', function () {
    Storage::fake('cdn', ['url' => 'https://cdn.test']);

    Http::fake([
        '*/projects/PRJ' => Http::response(['data' => []]),
        '*/contents*' => Http::response(['data' => [engineItemWithDiagram()], 'links' => ['next' => null]]),
        '*/confirm-published' => Http::response(['ok' => true]),
        'engine.test/*' => Http::response('image-bytes'),
    ]);

    $this->artisan('content-studio:sync')->assertSuccessful();
    expect(Article::first()->body_html)->not->toContain('https://cdn.test');

    config(['content-studio.disk' => 'cdn']);
    $this->artisan('content-studio:sync', ['--status' => 'all'])->assertSuccessful();

    Storage::disk('cdn')->assertExists('content_studio_images/1/hero.jpg');
    expect(Article::first()->body_html)->toContain('src="https://cdn.test/content_studio_images/1/diagrams/');
});

function engineItemWithDiagram(): array
{
    $id = '355f5250-3e28-435d-9a53-1d934c21a52f';

    return engineItem([
        'body_html' => "<p>Hallo</p><!--diagram-placeholder:{$id}-->",
        'image_placeholders' => [[
            'type' => 'diagram',
            'placeholder' => "<!--diagram-placeholder:{$id}-->",
            'image_url' => 'https://engine.test/diagram.png',
            'alt' => 'Schema',
        ]],
    ]);
}
