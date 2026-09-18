<?php

namespace Shazzoo\ContentStudio\Support;

class Tracking
{
    /** Events always go to the Engine, never to the site itself. */
    public const DEFAULT_ENDPOINT = 'https://engine.content-studio.com/api/tracking/event';

    /**
     * The published script, or the URL the site configured. Publish it with:
     *
     *     php artisan vendor:publish --tag=content-studio-assets
     */
    public static function scriptUrl(): string
    {
        $url = trim((string) config('content-studio.tracking.script_url', ''));

        return $url !== '' ? $url : asset('vendor/content-studio/tracking.js');
    }

    public static function enabled(): bool
    {
        return (bool) config('content-studio.tracking.enabled', true);
    }

    /**
     * Always an absolute Engine URL. A relative value would send the event
     * to the site itself, so it falls back to the default.
     */
    public static function endpoint(): string
    {
        $endpoint = trim((string) config('content-studio.tracking.endpoint', ''));

        if ($endpoint === '' || ! preg_match('#^https?://#i', $endpoint)) {
            return self::DEFAULT_ENDPOINT;
        }

        return $endpoint;
    }
}
