<?php

namespace Shazzoo\ContentStudio;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shazzoo\ContentStudio\Console\ClearArticlesCommand;
use Shazzoo\ContentStudio\Console\SyncArticlesCommand;
use Shazzoo\ContentStudio\Http\Controllers\ArticleController;
use Shazzoo\ContentStudio\Http\Controllers\SitemapController;
use Shazzoo\ContentStudio\Support\ArticleRoutes;

class ContentStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/content-studio.php', 'content-studio');
    }

    public function boot(): void
    {
        $base = dirname(__DIR__);

        $this->loadMigrationsFrom($base.'/database/migrations');
        $this->loadViewsFrom($base.'/resources/views', 'content-studio');
        $this->loadTranslationsFrom($base.'/resources/lang', 'content-studio');

        if (config('content-studio.register_routes')) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SyncArticlesCommand::class, ClearArticlesCommand::class]);

            $this->publishes([
                $base.'/config/content-studio.php' => config_path('content-studio.php'),
            ], 'content-studio-config');

            // The tracking script. Publish it again with --force after
            // updating the package, or the site keeps the old version.
            $this->publishes([
                $base.'/resources/js/tracking.js' => public_path('vendor/content-studio/tracking.js'),
            ], ['content-studio-assets', 'laravel-assets']);

            // Publish only the views you want to restyle; the rest keep coming
            // from the package, so package updates still apply to them.
            $this->publishes([
                $base.'/resources/views' => resource_path('views/vendor/content-studio'),
            ], 'content-studio-views');

            $this->publishes([
                $base.'/resources/lang' => lang_path('vendor/content-studio'),
            ], 'content-studio-lang');
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('content-studio:sync')
                ->everyFifteenMinutes()
                ->withoutOverlapping();
        });
    }

    private function registerRoutes(): void
    {
        $prefix = ArticleRoutes::prefix();

        Route::middleware('web')->group(function () use ($prefix) {
            Route::get($prefix, [ArticleController::class, 'index'])
                ->name('content-studio.index');

            // Before the {slug} route, which would otherwise catch this path.
            Route::get($prefix.'/sitemap.xml', SitemapController::class)
                ->name('content-studio.sitemap');

            Route::get($prefix.'/{slug}', [ArticleController::class, 'show'])
                ->name('content-studio.show');
        });
    }
}
