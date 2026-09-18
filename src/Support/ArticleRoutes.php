<?php

namespace Shazzoo\ContentStudio\Support;

final class ArticleRoutes
{
    public static function prefix(): string
    {
        return trim((string) config('content-studio.route'), '/') ?: 'blog';
    }

    public static function indexUrl(): string
    {
        return url(self::prefix());
    }

    public static function articleUrl(string $slug): string
    {
        return url(self::prefix().'/'.trim($slug, '/'));
    }
}
