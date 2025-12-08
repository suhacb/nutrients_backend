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
use App\Services\Search\SearchServiceContract;

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
        $userId = $this->user->id;
        $page = 1;
        $cacheKey = "search:{$userId}:ingredients:" . md5('protein bar') . ":page:$page";

        $cachedData = [
            'query' => 'protein bar',
            'index' => 'ingredients',
            'total' => 60,
            'per_page' => 25,
            'results' => [
                ['id' => 1, 'name' => 'Cached Item', 'description' => 'Test description', 'score' => 0.9]
            ],
        ];

        Cache::put($cacheKey, $cachedData, now()->addMinutes(30));

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('search'), [
            'query' => 'protein bar',
            'index' => 'ingredients',
            'page' => $page,
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

    public function test_it_caches_pages_separately_and_clears_on_query_change(): void
    {
        $this->mock(SearchServiceContract::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('ingredients', ['query_string' => ['query' => 'apple']], 25, 0)
                ->once()
                ->andReturn([
                    'total' => 50,
                    'hits' => array_map(fn($i) => [
                        '_id' => $i,
                        '_source' => ['name' => "Apple $i", 'description' => "Desc $i", 'score' => 1.0],
                    ], range(1, 25))
                ]);

            $mock->shouldReceive('search')
                ->with('ingredients', ['query_string' => ['query' => 'apple']], 25, 25)
                ->once()
                ->andReturn([
                    'total' => 50,
                    'hits' => array_map(fn($i) => [
                        '_id' => $i,
                        '_source' => ['name' => "Apple $i", 'description' => "Desc $i", 'score' => 1.0],
                    ], range(26, 50))
                ]);
        });

        // Page 1
        $response1 = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'apple', 'index' => 'ingredients', 'page' => 1]);

        $response1->assertOk()->assertJson(['page' => 1]);

        // Page 2
        $response2 = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'apple', 'index' => 'ingredients', 'page' => 2]);

        $response2->assertOk()->assertJson(['page' => 2]);

        // Both cache keys should exist
        $userId = Auth::user()->id;
        $page1Key = "search:{$userId}:ingredients:" . md5('apple') . ":page:1";
        $page2Key = "search:{$userId}:ingredients:" . md5('apple') . ":page:2";

        $this->assertTrue(Cache::has($page1Key));
        $this->assertTrue(Cache::has($page2Key));

        // Now change query → old pages should be cleared
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'banana', 'index' => 'ingredients', 'page' => 1]);

        $this->assertFalse(Cache::has($page1Key));
        $this->assertFalse(Cache::has($page2Key));
    }


    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
