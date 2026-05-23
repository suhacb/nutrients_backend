<?php

namespace App\Providers;

use App\Services\Conversion\UnitConversionService;
use App\Services\Conversion\UnitConversionServiceContract;
use Illuminate\Support\ServiceProvider;

class ConversionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UnitConversionServiceContract::class, UnitConversionService::class);
    }
}
