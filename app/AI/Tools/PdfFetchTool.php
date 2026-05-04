<?php

namespace App\AI\Tools;

use App\AI\Contracts\ToolContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class PdfFetchTool implements ToolContract
{
    public function __construct(
        private readonly int $timeout = 30,
    ) {}

    public function name(): string
    {
        return 'pdf_fetch';
    }

    public function description(): string
    {
        return 'Downloads a PDF file and returns its extracted text content.';
    }

    public function parameters(): array
    {
        return ['url' => 'string'];
    }

    public function run(array $args): mixed
    {
        $url = $args['url'];

        Log::debug('pdf_fetch.url', ['url' => $url]);

        try {
            $response = Http::timeout($this->timeout)->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new \RuntimeException("HTTP {$response->status()} fetching {$url}", $response->status());
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pdf_');

        try {
            file_put_contents($tmp, $response->body());
            $text = (new Parser())->parseFile($tmp)->getText();
        } finally {
            @unlink($tmp);
        }

        return preg_replace('/\n{3,}/', "\n\n", trim($text));
    }
}
