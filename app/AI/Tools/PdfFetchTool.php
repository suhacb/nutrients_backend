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

        $body     = $response->body();
        $maxBytes = config('ai.extraction.max_pdf_bytes');

        if (strlen($body) > $maxBytes) {
            throw new \RuntimeException("PDF at {$url} exceeds size limit ({$maxBytes} bytes), skipping");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pdf_');

        try {
            file_put_contents($tmp, $body);
            $text = (new Parser())->parseFile($tmp)->getText();
        } finally {
            @unlink($tmp);
        }

        $text = preg_replace('/\n{3,}/', "\n\n", trim($text));

        return static::chunkText(
            $text,
            config('ai.extraction.max_source_chars'),
            config('ai.extraction.chunk_overlap'),
            config('ai.extraction.max_chunks'),
        );
    }

    public static function chunkText(string $text, int $chunkSize, int $overlap, int $maxChunks): array
    {
        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        $stride = $chunkSize - $overlap;
        $chunks = [];
        $offset = 0;
        $length = mb_strlen($text);

        while ($offset < $length && count($chunks) < $maxChunks) {
            $chunks[] = mb_substr($text, $offset, $chunkSize);
            $offset  += $stride;
        }

        return $chunks;
    }
}
