<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests de Feature usan el TestCase de Laravel (app booteada); los de
| Unit son PHPUnit puro. Los fixtures golden-master viven en fixtures/golden
| y se acceden con el helper golden_path().
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function golden_path(string $path = ''): string
{
    return base_path('fixtures/golden'.($path !== '' ? '/'.ltrim($path, '/') : ''));
}
