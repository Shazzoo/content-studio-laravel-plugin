<?php

namespace Shazzoo\ContentStudio\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Shazzoo\ContentStudio\ContentStudio;
use Shazzoo\ContentStudio\ContentStudioServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ContentStudio::flushResolvers();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [ContentStudioServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.locale', 'nl');
        $app['config']->set('content-studio.api_key', 'test-key');
        $app['config']->set('content-studio.project_code', 'PRJ');
        $app['config']->set('content-studio.route', 'nieuws');
        $app['config']->set('content-studio.articles_per_page', 2);
    }
}
