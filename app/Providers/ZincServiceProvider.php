<?php

namespace App\Providers;

use App\Services\Search\SearchServiceContract;
use App\Services\Search\ZincSearchService;
use Illuminate\Support\ServiceProvider;

class ZincServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->singleton(SearchServiceContract::class, function ($app) {
            return new ZincSearchService([
                'base_uri' => config('zinc.base_url'),
                'username' => config('zinc.username'),
                'password' => config('zinc.password'),
            ]);
        });
    }
}
