<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Http\Requests\SearchRequest;
use Illuminate\Support\Facades\Cache;
use App\Services\Search\SearchServiceContract;
use Symfony\Component\HttpFoundation\JsonResponse;

class SearchController extends Controller
{
    protected SearchServiceContract $searchService;

    public function __construct(SearchServiceContract $searchService)
    {
        $this->searchService = $searchService;
    }
    
    public function search(SearchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $query = $data['query'];
        $index = $data['index'];
        $page = $request->page();
        $perPage = 25;

        $cacheKey = $request->cacheKey();

        if ($cached = Cache::get($cacheKey)) {
            $results = array_slice($cached['results'], ($page - 1) * $perPage, $perPage);
            return response()->json(array_merge($cached, [
                'page' => $page,
                'results' => $results,
            ]));
        }

        try {
            // Delegate the search to the service
            $offset = ($page - 1) * $perPage;
            $zincResults = $this->searchService->search($index, [
                'query_string' => ['query' => $query]
            ], $perPage, $offset);

            $total = $zincResults['total'] ?? 0;
            $hits = $zincResults['hits'] ?? [];

            $results = array_map(fn($hit) => [
                'id' => $hit['_id'] ?? null,
                'name' => $hit['_source']['name'] ?? null,
                'description' => $hit['_source']['description'] ?? null,
                'score' => $hit['_source']['score'] ?? null,
            ], $hits);

            // Cache first page
            if ($page === 1) {
                Cache::put($cacheKey, [
                    'query' => $query,
                    'index' => $index,
                    'total' => $total,
                    'per_page' => $perPage,
                    'results' => $results,
                ], now()->addMinutes(30));
            }

            return response()->json([
                'query' => $query,
                'index' => $index,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'results' => $results,
            ], 200);

        } catch (Exception $e) {
            return response()->json(['error' => 'ZincSearch unavailable'], 502);
        }
    }
}
