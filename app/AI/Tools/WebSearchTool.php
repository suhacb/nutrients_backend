<?php

namespace App\AI\Tools;

use App\AI\Contracts\ToolContract;
use App\Exceptions\LlmUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WebSearchTool implements ToolContract
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int    $limit,
    ) {}

    public function name(): string
    {
        return 'web_search';
    }

    public function description(): string
    {
        return 'Searches trusted nutrition sources for a given query and returns a list of relevant URLs with titles and snippets.';
    }

    public function parameters(): array
    {
        return ['query' => 'string'];
    }

    public function run(array $args): mixed
    {
        try {
            $response = Http::get("{$this->baseUrl}/search", [
                'q'      => $args['query'],
                'format' => 'json',
            ]);
        } catch (ConnectionException $e) {
            throw new LlmUnavailableException($e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new \RuntimeException(
                "SearXNG returned HTTP {$response->status()}",
                $response->status()
            );
        }

        $results = $response->json('results');

        if (!is_array($results)) {
            return [];
        }

        return array_map(
            fn ($result) => [
                'url'     => $result['url'],
                'title'   => $result['title'],
                'snippet' => $result['content'],
            ],
            array_slice($results, 0, $this->limit)
        );
    }
}
