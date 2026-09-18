# Content Studio for Laravel

Shows the blog articles from the Content Studio Strategy Engine on a plain Laravel site. The package syncs the articles into your database every 15 minutes and serves an overview page and an article page.

## Installation

```bash
composer require shazzoo/content-studio-laravel
php artisan migrate
php artisan vendor:publish --tag=content-studio-assets
```

The images from the Engine are stored on the `public` disk, so the site needs the storage symlink. Most Laravel projects already have it; if yours doesn't:

```bash
php artisan storage:link
```

The publish command copies the tracking script to `public/vendor/content-studio/tracking.js`. Without it the article pages ask for a file that isn't there and nothing is tracked.

### Keeping the script up to date

The script is published under the `laravel-assets` tag as well, which the default Laravel skeleton republishes on every `composer update`:

```json
"post-update-cmd": [
    "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
]
```

That covers `composer update`, but not `composer install`, and not a project whose `composer.json` lacks that script. To be sure, add this to your deploy:

```bash
php artisan vendor:publish --tag=content-studio-assets --force
```

A site that serves the file itself, through its own build or a CDN, can point the config at that URL instead and skip publishing:

```php
'tracking' => [
    'script_url' => 'https://cdn.example.com/tracking.js',
],
```

Add the settings to `.env`:

```dotenv
CONTENT_STUDIO_API_KEY=
CONTENT_STUDIO_PROJECT_CODE=
CONTENT_STUDIO_ROUTE=blog
CONTENT_STUDIO_ARTICLES_PER_PAGE=12
```

After storing an article, the sync reports it to the Engine as published, which takes it out of the Engine's `approved` list. Turn that off on a site that syncs the real project without being the live site, such as local or staging:

```dotenv
CONTENT_STUDIO_CONFIRM_PUBLISHED=false
```

The sync runs through the Laravel scheduler, so make sure `php artisan schedule:run` runs every minute. To sync right away:

```bash
php artisan content-studio:sync
php artisan content-studio:sync --status=all   # also fetch articles that are already live
```

To delete every synced article, for example to re-sync from scratch:

```bash
php artisan content-studio:clear            # asks for confirmation
php artisan content-studio:clear --force    # deletes straight away
php artisan content-studio:clear --images   # also deletes the downloaded images
```

Without `--images` the images stay in storage, so a following `--status=all` sync does not download them again.

An article whose images cannot be downloaded is not stored at all, so the site never shows a half-finished article; an article that is already live keeps the version it has. The command exits with an error, the article stays unconfirmed in the Engine's `approved` list, and the next sync downloads the images again. Images that did come through stay on disk, so a retry only fetches what is missing.

## Routes

| URL | Name |
| --- | --- |
| `/{route}` | `content-studio.index` |
| `/{route}/{slug}` | `content-studio.show` |
| `/{route}/sitemap.xml` | `content-studio.sitemap` |

Set `CONTENT_STUDIO_ROUTES=false` to register the blog routes yourself; see [Multiple languages](#multiple-languages).

Add the sitemap to your `robots.txt` or your own sitemap index:

```
Sitemap: https://example.com/blog/sitemap.xml
```

## Latest articles on other pages

```blade
<x-content-studio::latest-articles :limit="3" title="From the blog" />
```

Without a `title` the heading and the "All articles" link are left out. The component renders nothing when there are no articles.

## Using your own layout

By default the pages use a bare layout from the package. To render the blog inside your site, publish the config and point `layout` at your own layout:

```bash
php artisan vendor:publish --tag=content-studio-config
```

```php
'layout' => 'layouts.app',
```

Your layout needs `@yield('content')`, and `@stack('head')` inside `<head>` for the title, meta description, Open Graph tags and JSON-LD.

## Multiple languages

The blog is single language out of the box: it shows the articles in the main language of the Engine project, on the routes of the package. The sync always stores every language the Engine returns, so switching this on later needs no re-sync.

A site with multiple languages keeps its own way of working: its own routes, its own locale detection, its own URLs. The package asks the site for those three things instead of deciding them.

### 1. Register the routes yourself

```dotenv
CONTENT_STUDIO_ROUTES=false
```

The package then registers no blog routes, only the tracking script. Put the controller wherever it belongs, for example inside your own locale group:

```php
use Shazzoo\ContentStudio\Http\Controllers\ArticleController;

Route::prefix('{locale}')->whereIn('locale', ['nl', 'en'])->group(function () {
    Route::get('blog', [ArticleController::class, 'index'])->name('blog.index');
    Route::get('blog/{slug}', [ArticleController::class, 'show'])->name('blog.show');
});
```

The article route must have a parameter named `slug`; every other parameter is yours. Add your own middleware, a translated prefix or a subdomain as you like.

### 2. Say which language to show

```php
use Shazzoo\ContentStudio\ContentStudio;

// In AppServiceProvider::boot()
ContentStudio::resolveLocaleUsing(fn () => request()->route('locale') ?? app()->getLocale());
```

The overview, the article page, the sitemap and the `latest-articles` component all use this. The locale also becomes the app locale, so the package translations follow along. Default: the main language of the Engine project.

### 3. Say what the URLs look like

```php
ContentStudio::resolveUrlUsing(fn (Article $article) => route('blog.show', [
    'locale' => $article->locale,
    'slug' => $article->slug,
]));

ContentStudio::resolveIndexUrlUsing(fn (string $locale) => route('blog.index', ['locale' => $locale]));
```

Every link in the views runs through these, so the cards, the previous/next links, the breadcrumbs, the canonical URL and the sitemap all follow your routes. Default: `/{route}/{slug}` and `/{route}`.

### Translations of an article

Each language is its own record with its own slug, linked by the article id from the Engine. `translations()` finds the others, which is what you need for a language switcher or `hreflang` tags:

```blade
@foreach ($article->translations as $translation)
    <a href="{{ $translation->url() }}">{{ strtoupper($translation->locale) }}</a>
@endforeach
```

### What stays yours

- **Tracking:** the article view includes `content-studio::partials.tracking`. Write your own article view and you add it yourself:
  ```blade
  @include('content-studio::partials.tracking', ['article' => $article])
  ```
- **Sitemap:** the package sitemap covers one language. For several languages, build your own from `Article::query()->visible($locale)->get()`.
- **`hreflang` and the canonical per language:** add these to your own layout with `translations()`.
- **A missing translation** gives a 404. Publish the views if you want to fall back to another language.

## Styling

The views use Tailwind CSS (v4) with neutral default styling and `dark:` variants. Tailwind only generates classes from files it scans, so add the package views to your CSS entry file:

```css
@source '../../vendor/shazzoo/content-studio-laravel/resources/views';
```

The bundled layout loads `resources/css/app.css` through Vite when a build or dev server is present. Every element also has a `cs-*` class to hook your own CSS onto. To change the markup itself, publish the views:

```bash
php artisan vendor:publish --tag=content-studio-views
php artisan vendor:publish --tag=content-studio-lang
```

**Dark mode:** the views ship `dark:` variants, so a site that is always light and leaves Tailwind on its default strategy gets a dark blog whenever the visitor's system is dark. Switch Tailwind to the class-based variant to prevent that:

```css
@custom-variant dark (&:where(.dark, .dark *));
```

## Tests

```bash
composer test
```
