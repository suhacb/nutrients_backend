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
    protected int $perPage = 25;

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
        $userId = $request->user()->id ?? 0;

        // Handle query change and cache invalidation
        $lastQueryKey = "search_last_query:{$userId}:{$index}";
        $previousQuery = Cache::get($lastQueryKey);

        if ($previousQuery && $previousQuery !== $query) {
            $previousPagesListKey = "search_pages:{$userId}:{$index}:" . md5($previousQuery);
            foreach (Cache::get($previousPagesListKey, []) as $key) {
                Cache::forget($key);
            }
            Cache::forget($previousPagesListKey);
        }

        // Update last query
        Cache::put($lastQueryKey, $query, now()->addMinutes(30));

        // Cache per page
        $cacheKey = "search:{$userId}:{$index}:" . md5($query) . ":page:$page";
        if ($cached = Cache::get($cacheKey)) {
            return response()->json(array_merge($cached->toArray(), ['page' => $page]));
        }

        try {
            // Delegate search to the service
            $result = $this->searchService->search($index, $query, $this->perPage, $page);

            // Cache page
            Cache::put($cacheKey, $result, now()->addMinutes(30));

            // Track page keys
            $pagesListKey = "search_pages:{$userId}:{$index}:" . md5($query);
            $cachedPages = Cache::get($pagesListKey, []);
            if (!in_array($cacheKey, $cachedPages)) {
                $cachedPages[] = $cacheKey;
                Cache::put($pagesListKey, $cachedPages, now()->addMinutes(30));
            }

            return response()->json(array_merge($result->toArray(), ['page' => $page]), 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Search service unavailable'], 502);
        }
    }
}
