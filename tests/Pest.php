<?php

use Shazzoo\ContentStudio\Tests\TestCase;

// The placeholder tests are pure and need no application.
pest()->extend(TestCase::class)->in('Feature');
// A site that registers the blog routes itself.
pest()->extend(Shazzoo\ContentStudio\Tests\SiteRoutesTestCase::class)->in('SiteRoutes');
