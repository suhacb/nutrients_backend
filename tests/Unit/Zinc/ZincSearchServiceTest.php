<?php

namespace Tests\Unit\Zinc;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\Search\ZincSearchService;
use App\Services\Search\SearchServiceResponse;
use Exception;

class ZincSearchServiceTest extends TestCase
{
    protected ZincSearchService $service;
    protected string $baseUri;
    protected string $username;
    protected string $password;
    protected string $index = 'nutrients';
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->baseUri = config('zinc.base_url');
        $this->username = config('zinc.username');
        $this->password = config('zinc.password');
        $this->service = new ZincSearchService([
            'base_uri' => $this->baseUri,
            'username' => $this->username,
            'password' => $this->password,
        ]);
    }

    public function test_insert_sends_post_request_and_returns_true_on_success(): void
    {
        Http::fake([
            "{$this->baseUri}/api/{$this->index}/_doc" => Http::response([], 201)
        ]);

        $result = $this->service->insert($this->index, ['foo' => 'bar']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === "{$this->baseUri}/api/{$this->index}/_doc"
                && $request->method() === 'POST'
                && $request['foo'] === 'bar';
        });
    }

    public function test_insert_throws_exception_on_failure(): void
    {
        Http::fake([
            "{$this->baseUri}/api/{$this->index}/_doc" => Http::response([], 500)
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Search service unavailable');

        $this->service->insert($this->index, ['foo' => 'bar']);
    }

    public function test_update_sends_put_request_and_returns_true_on_success(): void
    {
        $id = 123;
        Http::fake([
            "{$this->baseUri}/api/{$this->index}/_doc/{$id}" => Http::response([], 200)
        ]);

        $result = $this->service->update($this->index, $id, ['foo' => 'baz']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) use ($id) {
            return $request->url() === "{$this->baseUri}/api/{$this->index}/_doc/{$id}"
                && $request->method() === 'PUT'
                && $request['foo'] === 'baz';
        });
    }

    public function test_delete_sends_delete_request_and_returns_true_on_success(): void
    {
        $id = 123;
        Http::fake([
            "{$this->baseUri}/api/{$this->index}/_doc/{$id}" => Http::response([], 200)
        ]);

        $result = $this->service->delete($this->index, $id);

        $this->assertTrue($result);

        Http::assertSent(function ($request) use ($id) {
            return $request->url() === "{$this->baseUri}/api/{$this->index}/_doc/{$id}"
                && $request->method() === 'DELETE';
        });
    }

    public function test_search_returns_searchserviceresponse_with_expected_fields(): void
    {
        $query = 'butter';

        Http::fake([
            "{$this->baseUri}/api/{$this->index}/_search" => Http::response([
                'hits' => [
                    'total' => 2,
                    'hits' => [
                        [
                            '_source' => [
                                'id' => 1,
                                'name' => 'Peanut Butter',
                                'description' => 'Smooth peanut butter'
                            ],
                            '_score' => 0.9,
                        ],
                        [
                            '_source' => [
                                'id' => 2,
                                'name' => 'Almond Butter',
                                'description' => 'Creamy almond butter'
                            ],
                            '_score' => 0.8,
                        ],
                    ],
                ]
            ], 200),
        ]);

        $result = $this->service->search($this->index, $query, 10, 1);

        $this->assertInstanceOf(SearchServiceResponse::class, $result);
        $this->assertEquals($query, $result->query);
        $this->assertEquals($this->index, $result->index);
        $this->assertEquals(2, $result->total);
        $this->assertCount(2, $result->results);
        $this->assertEquals('Peanut Butter', $result->results[0]['name']);

        Http::assertSent(function ($request) {
            return $request->url() === "{$this->baseUri}/api/{$this->index}/_search"
                && $request->method() === 'POST'
                && $request['query']['term'] === 'butter';
        });
    }

    public function test_search_returns_empty_response_when_query_is_blank(): void
    {
        $result = $this->service->search($this->index, '');

        $this->assertInstanceOf(SearchServiceResponse::class, $result);
        $this->assertEquals(0, $result->total);
        $this->assertEmpty($result->results);

        Http::assertNothingSent();
    }
}
