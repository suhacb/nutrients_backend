<?php

namespace App\AI\Tools;

use App\AI\Contracts\ToolContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class WebFetchTool implements ToolContract
{
    public function __construct(
        private readonly int $timeout = 15,
    ) {}

    public function name(): string
    {
        return 'web_fetch';
    }

    public function description(): string
    {
        return 'Fetches a web page and returns its main textual content, stripped of navigation, scripts, and boilerplate.';
    }

    public function parameters(): array
    {
        return ['url' => 'string'];
    }

    public function run(array $args): mixed
    {
        try {
            $response = Http::timeout($this->timeout)->get($args['url']);
        } catch (ConnectionException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new \RuntimeException("HTTP {$response->status()} fetching {$args['url']}", $response->status());
        }

        $crawler = new Crawler($response->body());

        foreach (['script', 'style', 'nav', 'header', 'footer'] as $tag) {
            $crawler->filter($tag)->each(fn (Crawler $node) => $node->getNode(0)?->parentNode?->removeChild($node->getNode(0)));
        }

        $paragraphs = null;

        foreach (['article p', 'main p', 'body p'] as $selector) {
            $nodes = $crawler->filter($selector);
            if ($nodes->count() > 0) {
                $paragraphs = $nodes;
                break;
            }
        }

        if ($paragraphs === null) {
            return '';
        }

        $text = $paragraphs->each(fn (Crawler $node) => trim($node->text()));
        $text = implode("\n\n", array_filter($text));

        return preg_replace(['/[ \t]+/', '/\n{3,}/'], [' ', "\n\n"], $text);
    }
}
