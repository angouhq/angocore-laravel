<?php

declare(strict_types=1);

namespace Angou\Angocore\Tests;

use Angou\Angocore\AngocoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AngocoreServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('angocore.base_url', 'https://core.angocore.test');
        $app['config']->set('angocore.api_key', 'ango_test_sdk');
    }
}
