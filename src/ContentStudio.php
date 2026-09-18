<?php

namespace Shazzoo\ContentStudio;

use Closure;
use Shazzoo\ContentStudio\Engine\ProjectInfo;
use Shazzoo\ContentStudio\Models\Article;
use Shazzoo\ContentStudio\Support\ArticleRoutes;

/**
 * The hooks a site uses to fit the blog into its own setup. Without them the
 * blog is single language on the routes of the package itself.
 */
class ContentStudio
{
    private static ?Closure $localeResolver = null;

    private static ?Closure $urlResolver = null;

    private static ?Closure $indexUrlResolver = null;

    /** The language the blog shows. Default: the main language of the Engine project. */
    public static function resolveLocaleUsing(?Closure $resolver): void
    {
        self::$localeResolver = $resolver;
    }

    /** The URL of one article. Default: /{route}/{slug}. */
    public static function resolveUrlUsing(?Closure $resolver): void
    {
        self::$urlResolver = $resolver;
    }

    /** The URL of the overview page. Default: /{route}. */
    public static function resolveIndexUrlUsing(?Closure $resolver): void
    {
        self::$indexUrlResolver = $resolver;
    }

    public static function locale(): string
    {
        if (self::$localeResolver) {
            return (string) (self::$localeResolver)();
        }

        return app(ProjectInfo::class)->primaryLocale() ?: app()->getLocale();
    }

    public static function url(Article $article): string
    {
        if (self::$urlResolver) {
            return (string) (self::$urlResolver)($article);
        }

        return ArticleRoutes::articleUrl((string) $article->slug);
    }

    public static function indexUrl(?string $locale = null): string
    {
        if (self::$indexUrlResolver) {
            return (string) (self::$indexUrlResolver)($locale ?? self::locale());
        }

        return ArticleRoutes::indexUrl();
    }

    /** Forget every hook. Useful between tests. */
    public static function flushResolvers(): void
    {
        self::$localeResolver = null;
        self::$urlResolver = null;
        self::$indexUrlResolver = null;
    }
}
