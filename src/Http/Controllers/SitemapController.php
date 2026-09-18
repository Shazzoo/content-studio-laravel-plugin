<?php

namespace Shazzoo\ContentStudio\Http\Controllers;

use Illuminate\Http\Response;
use Shazzoo\ContentStudio\ContentStudio;
use Shazzoo\ContentStudio\Models\Article;

class SitemapController
{
    public function __invoke(): Response
    {
        $articles = Article::query()
            ->visible(ContentStudio::locale())
            ->latest('generated_at')
            ->get(['slug', 'updated_at']);

        return response()
            ->view('content-studio::sitemap', ['articles' => $articles])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
