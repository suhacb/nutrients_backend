<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // In e2e test mode run all queued jobs inline so Zinc is updated before
        // the HTTP response returns. Without this, a delete/update would return
        // before Zinc reflects the change, causing searches to return stale data.
        if (config('app.test_mode')) {
            config(['queue.default' => 'sync']);
        }
    }
}
