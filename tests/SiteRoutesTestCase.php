<?php

namespace Shazzoo\ContentStudio\Tests;

/** A site that registers the blog routes itself. */
abstract class SiteRoutesTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('content-studio.register_routes', false);
    }
}
