<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class TestTeardownController extends Controller
{
    #[OA\Post(
        path: '/api/test/teardown',
        summary: 'Wipe e2e environment: drop all data, clear Zinc indices (E2E only)',
        tags: ['E2E'],
        responses: [
            new OA\Response(response: 200, description: 'Teardown complete', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'teardown', type: 'boolean', example: true)]
            )),
            new OA\Response(response: 404, description: 'Not available — APP_TEST_MODE is false'),
        ]
    )]
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
