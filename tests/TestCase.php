<?php

namespace Searsandrew\BriarRose\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Searsandrew\BriarRose\BriarRoseServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [BriarRoseServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('briar-rose.account', '5802217');
        $app['config']->set('briar-rose.consumer_key', 'ck');
        $app['config']->set('briar-rose.consumer_secret', 'cs');
        $app['config']->set('briar-rose.token_id', 'tk');
        $app['config']->set('briar-rose.token_secret', 'ts');
        $app['config']->set('briar-rose.rest_base_url', 'https://5802217.suitetalk.api.netsuite.com');
    }
}