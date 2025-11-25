<?php

namespace Tests\Unit\Zinc;


use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\Search\ZincSearchService;

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
            $this->baseUri . "/api/$this->index/_doc" => Http::response([], 201)
        ]);

        $result = $this->service->insert($this->index, ['foo' => 'bar']);
        logger()->info($result);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === $this->baseUri . "/api/$this->index/_doc"
                && $request->method() === 'POST'
                && $request['foo'] === 'bar';
        });
    }

    public function test_update_sends_put_request_and_returns_true_on_success(): void
    {
        $id = 123;
        Http::fake([
            $this->baseUri . "/api/$this->index/_doc/$id" => Http::response([], 200)
        ]);

        $result = $this->service->update($this->index, $id, ['foo' => 'baz']);

        $this->assertTrue($result);
        logger()->info($result);

        Http::assertSent(function ($request) use ($id) {
            return $request->url() === $this->baseUri . "/api/$this->index/_doc/$id"
                && $request->method() === 'PUT'
                && $request['foo'] === 'baz';
        });
    }

    public function test_delete_sends_delete_request_and_returns_true_on_success(): void
    {
        $id = 123;
        Http::fake([
            $this->baseUri . "/api/$this->index/_doc/$id" => Http::response([], 200)
        ]);

        $result = $this->service->delete($this->index, $id);
        logger()->info($result);

        $this->assertTrue($result);

        Http::assertSent(function ($request) use ($id) {
            return $request->url() === $this->baseUri . "/api/$this->index/_doc/$id"
                && $request->method() === 'DELETE';
        });
    }

    public function test_search_sends_post_request_and_returns_response_json(): void
    {
        Http::fake([
             $this->baseUri . "/api/{$this->index}/_search" => Http::response(['hits' => ['foo' => 'bar']], 200)
        ]);

        $result = $this->service->search($this->index, ['query' => 'test']);
        logger()->info($result);

        $this->assertEquals(['hits' => ['foo' => 'bar']], $result);

        Http::assertSent(function ($request) {
            return $request->url() === $this->baseUri . '/api/' . $this->index . '/_search'
                && $request->method() === 'POST'
                && $request['query'] === 'test';
        });
    }
}
