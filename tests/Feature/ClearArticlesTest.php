<?php

use Illuminate\Support\Facades\Storage;
use Shazzoo\ContentStudio\Models\Article;

function storedArticle(): Article
{
    static $id = 0;
    $id++;

    return Article::create([
        'content_studio_article_id' => $id,
        'locale' => 'nl',
        'title' => "Artikel {$id}",
        'slug' => "artikel-{$id}",
    ]);
}

it('deletes every article with --force', function () {
    storedArticle();
    storedArticle();

    $this->artisan('content-studio:clear --force')
        ->expectsOutputToContain('Deleted 2 articles')
        ->assertSuccessful();

    expect(Article::count())->toBe(0);
});

it('asks for confirmation and keeps the articles on no', function () {
    storedArticle();

    $this->artisan('content-studio:clear')
        ->expectsConfirmation('Delete 1 articles?', 'no')
        ->expectsOutputToContain('Cancelled')
        ->assertSuccessful();

    expect(Article::count())->toBe(1);
});

it('deletes after a confirmed yes', function () {
    storedArticle();

    $this->artisan('content-studio:clear')
        ->expectsConfirmation('Delete 1 articles?', 'yes')
        ->assertSuccessful();

    expect(Article::count())->toBe(0);
});

it('says there is nothing to delete', function () {
    $this->artisan('content-studio:clear')
        ->expectsOutputToContain('no articles to delete')
        ->assertSuccessful();
});

it('deletes the images with --images', function () {
    Storage::fake('public');
    Storage::disk('public')->put('content_studio_images/1/hero.jpg', 'bytes');
    Storage::disk('public')->put('other/keep.jpg', 'bytes');
    storedArticle();

    $this->artisan('content-studio:clear --images --force')
        ->expectsOutputToContain('Deleted 1 articles and their images')
        ->assertSuccessful();

    expect(Article::count())->toBe(0);
    Storage::disk('public')->assertMissing('content_studio_images/1/hero.jpg');
    Storage::disk('public')->assertExists('other/keep.jpg');
});

it('keeps the images without --images', function () {
    Storage::fake('public');
    Storage::disk('public')->put('content_studio_images/1/hero.jpg', 'bytes');
    storedArticle();

    $this->artisan('content-studio:clear --force')->assertSuccessful();

    Storage::disk('public')->assertExists('content_studio_images/1/hero.jpg');
});

it('mentions the images in the confirmation', function () {
    Storage::fake('public');
    Storage::disk('public')->put('content_studio_images/1/hero.jpg', 'bytes');
    storedArticle();

    $this->artisan('content-studio:clear --images')
        ->expectsConfirmation('Delete 1 articles and their images?', 'no')
        ->assertSuccessful();

    expect(Article::count())->toBe(1);
    Storage::disk('public')->assertExists('content_studio_images/1/hero.jpg');
});
