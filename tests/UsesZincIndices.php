<?php

namespace Tests;

use Illuminate\Support\Facades\Http;

trait UsesZincIndices
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createZincIndices();
    }

    protected function tearDown(): void
    {
        $this->deleteZincIndices();
        parent::tearDown();
    }

    private function createZincIndices(): void
    {
        $baseUrl  = config('zinc.base_url');
        $username = config('zinc.username');
        $password = config('zinc.password');

        foreach (config('zinc.index_definitions') as $key => $definition) {
            $name = config("zinc.indices.{$key}");

            Http::withBasicAuth($username, $password)
                ->delete("{$baseUrl}/api/index/{$name}");

            Http::withBasicAuth($username, $password)
                ->put("{$baseUrl}/api/index", array_merge(['name' => $name], $definition));
        }
    }

    private function deleteZincIndices(): void
    {
        $baseUrl  = config('zinc.base_url');
        $username = config('zinc.username');
        $password = config('zinc.password');

        foreach (config('zinc.indices') as $name) {
            Http::withBasicAuth($username, $password)
                ->delete("{$baseUrl}/api/index/{$name}");
        }
    }
}
