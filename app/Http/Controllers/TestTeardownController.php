<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class TestTeardownController extends Controller
{
    public function teardown(): JsonResponse
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->clearZincIndices();

        return response()->json(['teardown' => true]);
    }

    private function clearZincIndices(): void
    {
        $baseUrl  = config('zinc.base_url');
        $username = config('zinc.username');
        $password = config('zinc.password');

        foreach (config('zinc.index_definitions') as $key => $definition) {
            $name = config("zinc.indices.{$key}");
            Http::withBasicAuth($username, $password)->delete("{$baseUrl}/api/index/{$name}");
            Http::withBasicAuth($username, $password)->put("{$baseUrl}/api/index", array_merge(['name' => $name], $definition));
        }
    }
}
