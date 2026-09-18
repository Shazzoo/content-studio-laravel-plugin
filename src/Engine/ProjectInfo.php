<?php

namespace Shazzoo\ContentStudio\Engine;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Project details from the Engine (locales, content counts), cached after
 * each sync so page requests never call the Engine.
 */
class ProjectInfo
{
    private const CACHE_KEY = 'content-studio.project';

    public function __construct(private EngineClient $engine) {}

    public function refresh(): ?array
    {
        try {
            $response = $this->engine->project();
        } catch (\Throwable $e) {
            Log::warning('[ContentStudio] Could not fetch project details: '.$e->getMessage());

            return null;
        }

        $data = $response->json('data');

        if ($response->failed() || ! is_array($data)) {
            Log::warning('[ContentStudio] Could not fetch project details', ['status' => $response->status()]);

            return null;
        }

        Cache::forever(self::CACHE_KEY, $data);

        return $data;
    }

    public function primaryLocale(): ?string
    {
        $locale = Cache::get(self::CACHE_KEY)['primary_locale'] ?? null;

        return filled($locale) ? (string) $locale : null;
    }

    public function syncableTotal(): ?int
    {
        $total = Cache::get(self::CACHE_KEY)['content_counts']['total'] ?? null;

        return $total === null ? null : (int) $total;
    }
}
