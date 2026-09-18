<?php

namespace Shazzoo\ContentStudio\Engine;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EngineClient
{
    public function configured(): bool
    {
        return filled(config('content-studio.api_key')) && filled(config('content-studio.project_code'));
    }

    public function project(): Response
    {
        return $this->request()->get($this->projectUrl());
    }

    /**
     * One page of contents. Pass the `links.next` URL of the previous page to
     * continue; the Engine carries the status filter along in that link.
     */
    public function contents(?string $status = null, ?string $nextUrl = null): Response
    {
        if ($nextUrl !== null) {
            return $this->request()->get($this->absolute($nextUrl));
        }

        $query = filled($status) && $status !== 'all' ? ['status' => $status] : [];

        return $this->request()->get($this->projectUrl().'/contents', $query);
    }

    public function confirmPublished(int|string $contentId, ?string $publishedUrl): Response
    {
        return $this->request()->timeout(10)->post(
            $this->projectUrl()."/contents/{$contentId}/confirm-published",
            array_filter(['published_url' => $publishedUrl]),
        );
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('content-studio.api_key'))
            ->acceptJson()
            ->timeout(20);
    }

    private function projectUrl(): string
    {
        return $this->apiUrl().'/projects/'.config('content-studio.project_code');
    }

    private function apiUrl(): string
    {
        return rtrim((string) config('content-studio.engine.api_url'), '/');
    }

    private function absolute(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return $this->apiUrl().'/'.ltrim($url, '/');
    }
}
