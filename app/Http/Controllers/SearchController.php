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
        $userId = $request->user()->id ?? 0;

        // Track previous query to clear old cache
        $lastQueryKey = "search_last_query:{$userId}:{$index}";
        $previousQuery = Cache::get($lastQueryKey);

        if ($previousQuery && $previousQuery !== $query) {
            // Get list of cached pages for previous query
            $previousPagesListKey = "search_pages:{$userId}:{$index}:" . md5($previousQuery);
            $cachedPages = Cache::get($previousPagesListKey, []);

            // Forget all cached pages
            foreach ($cachedPages as $key) {
                Cache::forget($key);
            }

            // Remove the page list itself
            Cache::forget($previousPagesListKey);
        }

        // Update last query
        Cache::put($lastQueryKey, $query, now()->addMinutes(30));

        // Build cache key for current page
        $cacheKey = "search:{$userId}:{$index}:" . md5($query) . ":page:$page";

        // Check if this page is already cached
        if ($cached = Cache::get($cacheKey)) {
            return response()->json(array_merge($cached, [
                'page' => $page,
                'results' => $cached['results'],
            ]));
        }

        try {
            $offset = ($page - 1) * $perPage;

            // Delegate search to the service
            $zincResults = $this->searchService->search(
                $index,
                ['query_string' => ['query' => $query]],
                $perPage,
                $offset
            );

            $total = $zincResults['total'] ?? 0;
            $hits = $zincResults['hits'] ?? [];

            $results = array_map(fn($hit) => [
                'id' => $hit['_id'] ?? null,
                'name' => $hit['_source']['name'] ?? null,
                'description' => $hit['_source']['description'] ?? null,
                'score' => $hit['_source']['score'] ?? null,
            ], $hits);

            // Cache this page
            $pageData = [
                'query' => $query,
                'index' => $index,
                'total' => $total,
                'per_page' => $perPage,
                'results' => $results,
            ];
            Cache::put($cacheKey, $pageData, now()->addMinutes(30));

            // Track this page key for the current query
            $pagesListKey = "search_pages:{$userId}:{$index}:" . md5($query);
            $cachedPages = Cache::get($pagesListKey, []);
            if (!in_array($cacheKey, $cachedPages)) {
                $cachedPages[] = $cacheKey;
                Cache::put($pagesListKey, $cachedPages, now()->addMinutes(30));
            }

            return response()->json(array_merge($pageData, ['page' => $page]), 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'ZincSearch unavailable'], 502);
        }
    }
}
