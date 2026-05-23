<?php

namespace Tests\Feature\Search;

use Tests\TestCase;
use App\Models\User;
use Tests\LoginTestUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Services\Search\SearchServiceContract;
use App\Services\Search\SearchServiceResponse;

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
            'index' => config('zinc.indices.ingredients'),
            'page' => 1,
            'per_page' => 25,
        ]);
    }

    public function test_it_uses_cache_when_available(): void
    {
        $index = config('zinc.indices.ingredients');
        $userId = $this->user->id;
        $page = 1;
        $cacheKey = "search:{$userId}:{$index}:" . md5('protein bar') . ":page:$page";

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

        $response->assertStatus(502)
                 ->assertJson(['error' => 'Search service unavailable']);
    }

    public function test_it_caches_pages_separately_and_clears_on_query_change(): void
    {
        $index = config('zinc.indices.ingredients');

        $this->mock(SearchServiceContract::class, function ($mock) use ($index) {
            $mock->shouldReceive('search')
                ->with($index, 'apple', 25, 1)
                ->once()
                ->andReturn(new SearchServiceResponse(
                    query: 'apple', index: $index, total: 50, perPage: 25,
                    results: array_map(fn($i) => ['id' => $i, 'name' => "Apple $i", 'description' => "Desc $i", 'score' => 1.0], range(1, 25))
                ));

            $mock->shouldReceive('search')
                ->with($index, 'apple', 25, 2)
                ->once()
                ->andReturn(new SearchServiceResponse(
                    query: 'apple', index: $index, total: 50, perPage: 25,
                    results: array_map(fn($i) => ['id' => $i, 'name' => "Apple $i", 'description' => "Desc $i", 'score' => 1.0], range(26, 50))
                ));

            $mock->shouldReceive('search')
                ->with($index, 'banana', 25, 1)
                ->once()
                ->andReturn(new SearchServiceResponse(
                    query: 'banana', index: $index, total: 20, perPage: 25,
                    results: array_map(fn($i) => ['id' => $i, 'name' => "Banana $i", 'description' => "Desc $i", 'score' => 0.9], range(1, 20))
                ));
        });

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'apple', 'index' => 'ingredients', 'page' => 1])
            ->assertOk()->assertJson(['page' => 1]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'apple', 'index' => 'ingredients', 'page' => 2])
            ->assertOk()->assertJson(['page' => 2]);

        $userId    = Auth::user()->id;
        $page1Key  = "search:{$userId}:{$index}:" . md5('apple') . ":page:1";
        $page2Key  = "search:{$userId}:{$index}:" . md5('apple') . ":page:2";

        $this->assertTrue(Cache::has($page1Key));
        $this->assertTrue(Cache::has($page2Key));

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'banana', 'index' => 'ingredients', 'page' => 1]);

        $this->assertFalse(Cache::has($page1Key));
        $this->assertFalse(Cache::has($page2Key));
    }

    public function test_it_handles_multiple_keywords_and_case_insensitive_search(): void
    {
        $index = config('zinc.indices.ingredients');
        $this->mock(SearchServiceContract::class, function ($mock) use ($index) {
            $mock->shouldReceive('search')
                ->with($index, 'Protein Bar', 25, 1)
                ->once()
                ->andReturn(
                    new SearchServiceResponse(
                        query: 'Protein Bar',
                        index: $index,
                        total: 2,
                        perPage: 25,
                        results: [
                            ['id' => 1, 'name' => 'Protein Bar Chocolate', 'description' => null, 'score' => 1.0],
                            ['id' => 2, 'name' => 'Protein bar vanilla', 'description' => null, 'score' => 0.9],
                        ]
                    )
                );
        });

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'Protein Bar', 'index' => 'ingredients', 'page' => 1]);

        $response->assertOk()->assertJsonCount(2, 'results');
    }

    public function test_it_returns_correct_from_to_metadata(): void
    {
        $index = config('zinc.indices.ingredients');
        $this->mock(SearchServiceContract::class, function ($mock) use ($index) {
            $mock->shouldReceive('search')
                ->with($index, 'apple', 25, 2)
                ->once()
                ->andReturn(
                    new SearchServiceResponse(
                        query: 'apple',
                        index: $index,
                        total: 50,
                        perPage: 25,
                        results: array_map(fn($i) => [
                            'id' => $i,
                            'name' => "Apple $i",
                            'description' => null,
                            'score' => 1.0,
                        ], range(26, 50))
                    )
                );
        });

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'apple', 'index' => 'ingredients', 'page' => 2]);

        $response->assertOk()->assertJson([
            'from' => 26,
            'to' => 50,
            'current_page' => 2,
        ]);
    }

    public function test_search_maps_request_index_name_through_config(): void
    {
        Config::set('zinc.indices.ingredients', 'custom_ingredients_index');

        $this->mock(SearchServiceContract::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('custom_ingredients_index', 'olive', 25, 1)
                ->once()
                ->andReturn(new SearchServiceResponse(
                    query: 'olive',
                    index: 'custom_ingredients_index',
                    total: 1,
                    perPage: 25,
                    results: [['id' => 1, 'name' => 'Olive Oil', 'description' => null, 'score' => 1.0]],
                ));
        });

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('search'), ['query' => 'olive', 'index' => 'ingredients', 'page' => 1])
            ->assertOk()
            ->assertJson(['index' => 'custom_ingredients_index']);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
