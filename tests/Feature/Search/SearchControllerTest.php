<?php

namespace Tests\Feature\Search;

use Tests\TestCase;
use App\Models\User;
use Tests\LoginTestUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\WithFaker;

class SearchControllerTest extends TestCase
{
    use LoginTestUser;

    protected string $zincBaseUrl;
    protected string $zincUsername;
    protected string $zincPassword;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zincBaseUrl = Config::get('zinc.base_url');
        $this->zincUsername = Config::get('zinc.username');
        $this->zincPassword = Config::get('zinc.password');
        $this->login();
        $this->user = Auth::user();
    }

    public function test_it_returns_paginated_results_from_zincsearch(): void
    {
        Http::fake([
            "{$this->zincBaseUrl}/*" => Http::response([
                'hits' => [
                    'total' => 60,
                    'hits' => collect(range(1, 25))->map(fn ($i) => [
                        '_id' => $i,
                        '_source' => [
                            'name' => "Item $i",
                            'description' => "Description for item $i",
                            'score' => 0.8 + $i / 100,
                        ],
                    ]),
                ],
            ], 200)
        ]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('search'), [
            'query' => 'protein bar',
            'index' => 'ingredients',
            'page' => 1,
        ]);

        $response->assertOk()->assertJsonStructure([
            'query', 'index', 'page', 'total', 'per_page', 'results' => [
                '*' => ['id', 'name', 'description', 'score']
            ]
        ])->assertJson([
            'query' => 'protein bar',
            'index' => 'ingredients',
            'page' => 1,
            'per_page' => 25,
        ]);
    }

    public function test_it_uses_cache_when_available(): void
    {
        $cacheKey = 'search:' . $this->user->id . ':ingredients:' . md5('protein bar');
        $cachedData = [
            'query' => 'protein bar',
            'index' => 'ingredients',
            'page' => 1,
            'total' => 60,
            'per_page' => 25,
            'results' => [['id' => 1, 'name' => 'Cached Item', 'score' => 0.9]],
        ];

        Cache::put($cacheKey, $cachedData, now()->addMinutes(30));

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('search'), [
            'query' => 'protein bar',
            'index' => 'ingredients',
            'page' => 1,
        ]);

        $response->assertOk()
                 ->assertJsonFragment(['name' => 'Cached Item']);
    }

    public function test_it_returns_validation_error_for_missing_parameters(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('search'), []);

        $response->assertStatus(422)->assertJsonValidationErrors(['query', 'index']);
    }

    public function test_it_handles_zincsearch_unavailability_gracefully(): void
    {
        Http::fake([
            "{$this->zincBaseUrl}/*" => Http::response(null, 500),
        ]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('search'), [
            'query' => 'apple',
            'index' => 'ingredients',
            'page' => 1,
        ]);

        $response->assertStatus(502)->assertJson(['error' => 'ZincSearch unavailable']);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
