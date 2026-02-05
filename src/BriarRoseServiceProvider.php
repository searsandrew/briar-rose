<?php

namespace Searsandrew\BriarRose;

use Illuminate\Support\ServiceProvider;
use Searsandrew\BriarRose\Clients\RestletClient;

class BriarRoseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/briar-rose.php', 'briar-rose');

        $this->app->singleton(BriarRoseManager::class, function () {
            return new BriarRoseManager(
                account: (string) config('briar-rose.account'),
                consumerKey: (string) config('briar-rose.consumer_key'),
                consumerSecret: (string) config('briar-rose.consumer_secret'),
                tokenId: (string) config('briar-rose.token_id'),
                tokenSecret: (string) config('briar-rose.token_secret'),
                restletBaseUrl: (string) config('briar-rose.restlet_base_url'),
                restBaseUrl: (string) config('briar-rose.rest_base_url'),
                timeout: (int) config('briar-rose.timeout', 30),
                connectTimeout: (int) config('briar-rose.connect_timeout', 10),
                logRequests: (bool) config('briar-rose.log_requests', false),
            );
        });

        $this->app->alias(BriarRoseManager::class, 'briar-rose');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/briar-rose.php' => config_path('briar-rose.php'),
        ], 'briar-rose-config');
    }
}