<?php

namespace Shazzoo\ContentStudio\Engine;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Shazzoo\ContentStudio\Models\Article;
use Shazzoo\ContentStudio\Support\ArticleImagePlaceholders;

class SyncArticles
{
    public const SYNCABLE_STATUSES = ['approved', 'published'];

    /** Set while an article is processed, when one of its images fails to download. */
    private bool $imageDownloadFailed = false;

    public function __construct(
        private EngineClient $engine,
        private ProjectInfo $projectInfo,
    ) {}

    /**
     * @param  string  $status  Status filter for the Engine: approved, published,
     *                          a comma list, or "all" to also fetch articles
     *                          that were already confirmed as published.
     * @return array{ok: bool, message: string, synced: int, skipped: int, pages: int, expected: ?int, errors: array}
     */
    public function __invoke(string $status = 'approved'): array
    {
        $stats = [
            'ok' => true,
            'message' => 'Articles synced with the Content Studio Engine.',
            'synced' => 0,
            'skipped' => 0,
            'pages' => 0,
            'expected' => null,
            'errors' => [],
        ];

        if (! $this->engine->configured()) {
            $stats['ok'] = false;
            $stats['message'] = 'Missing CONTENT_STUDIO_API_KEY and/or CONTENT_STUDIO_PROJECT_CODE.';
            Log::error('[ContentStudio] '.$stats['message']);

            return $stats;
        }

        $this->projectInfo->refresh();

        $nextUrl = null;

        do {
            $stats['pages']++;

            try {
                $response = $this->engine->contents($status, $nextUrl);
            } catch (\Throwable $e) {
                $stats['errors'][] = ['status' => 'connection_error', 'response' => $e->getMessage()];
                break;
            }

            if ($response->failed()) {
                $stats['errors'][] = [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'url' => (string) $response->effectiveUri(),
                ];
                break;
            }

            foreach ((array) $response->json('data') as $item) {
                if (! $this->shouldSync($item)) {
                    $stats['skipped']++;

                    continue;
                }

                if ($this->processArticle($item, $stats)) {
                    $stats['synced']++;
                }
            }

            $nextUrl = $response->json('links.next');
        } while ($nextUrl);

        foreach ($stats['errors'] as $error) {
            Log::error('[ContentStudio] Error while syncing with the Content Studio Engine', $error);
        }

        $stats['expected'] = $this->projectInfo->syncableTotal();
        $processed = $stats['synced'] + $stats['skipped'];

        if ($stats['errors'] !== []) {
            $stats['ok'] = false;
            $stats['message'] = 'Sync finished with errors.';
        } elseif ($stats['expected'] !== null && $processed < $stats['expected']) {
            $stats['ok'] = false;
            $stats['message'] = 'Sync finished, but '.($stats['expected'] - $processed).' of the '.$stats['expected'].' articles were not processed.';
            Log::warning('[ContentStudio] '.$stats['message']);
        }

        return $stats;
    }

    private function shouldSync(mixed $item): bool
    {
        $status = strtolower(trim((string) ($item['status'] ?? '')));

        return in_array($status, self::SYNCABLE_STATUSES, true);
    }

    /**
     * @param  array{errors: array}  $stats
     */
    private function processArticle(array $item, array &$stats): bool
    {
        $id = $item['id'] ?? null;
        $this->imageDownloadFailed = false;

        try {
            $article = DB::transaction(function () use ($item) {
                $article = $this->storeArticle($item);

                // An article without its images is not stored at all, so the
                // site never shows a half-finished article. What was
                // downloaded stays on disk for the next attempt.
                if ($this->imageDownloadFailed) {
                    throw new ImageDownloadFailed;
                }

                return $article;
            });
        } catch (ImageDownloadFailed) {
            Log::warning('[ContentStudio] Article not stored: an image could not be downloaded', [
                'content_studio_article_id' => $id,
            ]);

            $stats['errors'][] = [
                'status' => 'image_download_failed',
                'content_studio_article_id' => $id,
            ];

            return false;
        } catch (\Throwable $e) {
            Log::error('[ContentStudio] Error while processing article: '.$e->getMessage(), [
                'content_studio_article_id' => $id,
            ]);

            $stats['errors'][] = [
                'status' => 'article_failed',
                'response' => $e->getMessage(),
                'content_studio_article_id' => $id,
            ];

            return false;
        }

        if (config('content-studio.confirm_published')) {
            $this->confirmPublished($article);
        }

        return true;
    }

    private function storeArticle(array $item): Article
    {
        $id = $item['id'] ?? null;
        $title = $item['title'] ?? 'No title';

        $body = $item['body_html'] ?? null;
        if (is_string($body)) {
            $body = ArticleImagePlaceholders::replace(
                $body,
                $item,
                fn (string $url, ?string $placeholderId, array $entry): string => $this->storePlaceholderImage(
                    $url,
                    $id,
                    $placeholderId,
                    is_string($entry['type'] ?? null) ? $entry['type'] : 'image',
                ),
            );
        }

        $locale = str_replace('_', '-', strtolower(trim((string) ($item['locale'] ?? ''))));
        $locale = substr($locale !== '' ? $locale : (string) config('app.locale'), 0, 10);

        $slug = $item['slug'] ?? null;
        if (! filled($slug)) {
            $slug = (Str::slug((string) $title) ?: 'article').'-'.$id;
        }

        return Article::updateOrCreate(
            [
                'content_studio_article_id' => $id,
                'locale' => $locale,
            ],
            [
                'title' => $title,
                'excerpt' => $item['excerpt'] ?? null,
                'body_html' => $body,
                'featured_image_url' => $this->storeFeaturedImage($item['featured_image_url'] ?? null, $id),
                'featured_image_alt' => $item['featured_image_alt'] ?? null,
                'meta_description' => $item['meta_description'] ?? null,
                'seo_title' => $item['seo_title'] ?? null,
                'og_title' => $item['og_title'] ?? null,
                'og_description' => $item['og_description'] ?? null,
                'twitter_title' => $item['twitter_title'] ?? null,
                'twitter_description' => $item['twitter_description'] ?? null,
                'slug' => $slug,
                'cta' => $item['cta'] ?? null,
                'primary_keyword' => $item['primary_keyword'] ?? null,
                'cluster' => $item['clusters'] ?? null,
                'funnel_stage' => $item['funnel_stage'] ?? null,
                'intent' => $item['intent'] ?? null,
                'angle' => $item['angle'] ?? null,
                'source_month' => $item['source_month'] ?? null,
                'generated_at' => $item['generated_at'] ?? null,
                'planned_at' => $item['planned_at'] ?? null,
                'content_type' => $item['content_type'] ?? null,
                'cluster_key' => $item['cluster_key'] ?? null,
                'hub_content_id' => $item['hub_content_id'] ?? null,
                'author_name' => $item['author_name'] ?? null,
                'author_role_title' => $item['author_role_title'] ?? null,
                'author_experience_label' => $item['author_experience_label'] ?? null,
                'author_experience_summary' => $item['author_experience_summary'] ?? null,
                'author_article_relevance' => $item['author_article_relevance'] ?? null,
                'author_boundary_note' => $item['author_boundary_note'] ?? null,
            ],
        );
    }

    /**
     * Stores the featured image on the public disk and returns its path. The
     * path is based on the source file name, so an existing file means the
     * image is already there; a replaced image gets a new name.
     *
     * A failed download marks the article, which then is not stored at all,
     * so the site keeps showing the version it already had.
     */
    private function storeFeaturedImage(mixed $url, mixed $contentId): ?string
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = pathinfo($path, PATHINFO_FILENAME) ?: (string) $contentId;
        $uploadPath = "content_studio_images/{$contentId}/{$filename}.{$extension}";

        if (Storage::disk('public')->exists($uploadPath)) {
            return $uploadPath;
        }

        try {
            $response = Http::timeout(20)->get($url);
        } catch (\Throwable $e) {
            Log::warning('[ContentStudio] Featured image download failed', ['url' => $url, 'error' => $e->getMessage()]);
            $this->imageDownloadFailed = true;

            return null;
        }

        if ($response->failed()) {
            Log::warning('[ContentStudio] Featured image download failed', ['url' => $url, 'status' => $response->status()]);
            $this->imageDownloadFailed = true;

            return null;
        }

        Storage::disk('public')->put($uploadPath, $response->body());

        return $uploadPath;
    }

    private function storePlaceholderImage(string $imageUrl, mixed $contentId, ?string $placeholderId, string $type): string
    {
        if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $imageUrl;
        }

        $directory = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($contentId ?: 'unknown'));
        // The source URL is part of the file name, so a replaced image on the
        // same placeholder gets a new path.
        $urlHash = substr(sha1($imageUrl), 0, 12);
        $filename = $placeholderId
            ? preg_replace('/[^A-Za-z0-9_-]/', '-', $placeholderId).'-'.$urlHash
            : $urlHash;
        $folder = preg_replace('/[^a-z0-9-]/', '', strtolower($type)) ?: 'image';
        $extension = $this->extensionFromUrl($imageUrl);

        // Without a usable extension in the URL the response Content-Type is
        // needed, so there is nothing to check yet.
        if ($extension !== null) {
            $path = "content_studio_images/{$directory}/{$folder}s/{$filename}.{$extension}";

            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->url($path);
            }
        }

        try {
            $response = Http::timeout(20)->get($imageUrl);
        } catch (\Throwable $e) {
            Log::warning('[ContentStudio] Placeholder image download failed', ['url' => $imageUrl, 'error' => $e->getMessage()]);
            $this->imageDownloadFailed = true;

            return $imageUrl;
        }

        if ($response->failed()) {
            Log::warning('[ContentStudio] Placeholder image download failed', ['url' => $imageUrl, 'status' => $response->status()]);
            $this->imageDownloadFailed = true;

            return $imageUrl;
        }

        $extension ??= $this->extensionFromContentType($response->header('Content-Type'));
        $path = "content_studio_images/{$directory}/{$folder}s/{$filename}.{$extension}";

        Storage::disk('public')->put($path, $response->body());

        return Storage::disk('public')->url($path);
    }

    private function extensionFromUrl(string $url): ?string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        return null;
    }

    private function extensionFromContentType(?string $contentType): string
    {
        return match (true) {
            str_contains((string) $contentType, 'image/jpeg') => 'jpg',
            str_contains((string) $contentType, 'image/png') => 'png',
            str_contains((string) $contentType, 'image/gif') => 'gif',
            str_contains((string) $contentType, 'image/webp') => 'webp',
            default => 'svg',
        };
    }

    /**
     * Tells the Engine the article is live, so it drops out of the "approved"
     * list on the next sync. A failure is logged and retried next sync.
     */
    private function confirmPublished(Article $article): void
    {
        $publishedUrl = $article->url();
        // The Engine stores published_url as string(255).
        $publishedUrl = strlen($publishedUrl) > 255 ? null : $publishedUrl;

        if ($article->published_confirmed_at && $article->published_url === $publishedUrl) {
            return;
        }

        try {
            $response = $this->engine->confirmPublished($article->content_studio_article_id, $publishedUrl);
        } catch (\Throwable $e) {
            Log::warning('[ContentStudio] Could not confirm publication: '.$e->getMessage());

            return;
        }

        if ($response->failed()) {
            Log::warning('[ContentStudio] Could not confirm publication', [
                'content_studio_article_id' => $article->content_studio_article_id,
                'status' => $response->status(),
            ]);

            return;
        }

        $article->forceFill([
            'published_url' => $publishedUrl,
            'published_confirmed_at' => now(),
        ])->save();
    }
}
