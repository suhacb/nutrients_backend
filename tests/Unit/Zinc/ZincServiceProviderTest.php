<?php

namespace Tests\Unit\Zinc;

use Tests\TestCase;
use App\Providers\ZincServiceProvider;
use App\Services\Search\ZincSearchService;
use App\Services\Search\SearchServiceContract;

class ZincServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ZincServiceProvider::class];
    }

    public function test_it_binds_search_service_contract_to_zinc_service(): void
    {
        $service = $this->app->make(SearchServiceContract::class);

        $this->assertInstanceOf(ZincSearchService::class, $service);
    }
}
