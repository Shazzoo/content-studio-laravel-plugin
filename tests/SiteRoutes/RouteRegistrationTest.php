<?php

use Illuminate\Support\Facades\Route;

it('does not register the package routes when the site turns them off', function () {
    expect(Route::has('content-studio.index'))->toBeFalse()
        ->and(Route::has('content-studio.show'))->toBeFalse()
        ->and(Route::has('content-studio.sitemap'))->toBeFalse();
});
