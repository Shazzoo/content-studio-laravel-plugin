<?php

return [
    // Credentials of the project in the Content Studio Strategy Engine.
    'api_key' => env('CONTENT_STUDIO_API_KEY'),
    'project_code' => env('CONTENT_STUDIO_PROJECT_CODE'),

    // URL path the blog lives under, e.g. "blog" gives /blog and /blog/{slug}.
    'route' => env('CONTENT_STUDIO_ROUTE', 'blog'),

    // Turn this off to register the blog routes yourself, for example inside
    // your own locale group. See "Multiple languages" in the README.
    'register_routes' => (bool) env('CONTENT_STUDIO_ROUTES', true),

    // Report synced articles to the Engine as published, which takes them out
    // of its "approved" list. Turn this off on a site that syncs the real
    // project without being the live site, such as local or staging.
    'confirm_published' => (bool) env('CONTENT_STUDIO_CONFIRM_PUBLISHED', true),

    'articles_per_page' => (int) env('CONTENT_STUDIO_ARTICLES_PER_PAGE', 12),

    // Blade layout the blog pages extend. The layout must yield "content"
    // and should render @stack('head') inside <head> for the SEO tags.
    'layout' => 'content-studio::layout',

    'engine' => [
        'api_url' => 'https://engine.content-studio.com/api/v1',
    ],

    'tracking' => [
        'enabled' => true,
        'endpoint' => 'https://engine.content-studio.com/api/tracking/event',

        // Leave empty to use the published script in public/vendor/content-studio.
        'script_url' => '',
    ],
];
