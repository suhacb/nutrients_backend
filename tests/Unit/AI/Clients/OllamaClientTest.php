<?php

namespace Tests\Unit\AI\Clients;

use App\AI\Clients\OllamaClient;
use App\Exceptions\LlmRequestFailedException;
use App\Exceptions\LlmUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaClientTest extends TestCase
{
    private OllamaClient $client;

    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseUrl = config('ai.ollama.base_url');

        $this->client = new OllamaClient(
            baseUrl: $this->baseUrl,
            model:   config('ai.ollama.model'),
            timeout: config('ai.ollama.timeout'),
        );
    }

    // -------------------------------------------------------------------------
    // generate()
    // -------------------------------------------------------------------------

    public function test_generate_returns_response_text_on_success(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/generate" => Http::response(
                ['response' => 'Vitamin C is an essential nutrient.'],
                200
            ),
        ]);

        $result = $this->client->generate('Describe vitamin C.');

        $this->assertSame('Vitamin C is an essential nutrient.', $result);
    }

    public function test_generate_throws_request_failed_on_http_error(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/generate" => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(LlmRequestFailedException::class);

        $this->client->generate('Describe vitamin C.');
    }

    public function test_generate_http_error_carries_status_code(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/generate" => Http::response('', 503),
        ]);

        try {
            $this->client->generate('Describe vitamin C.');
            $this->fail('Expected LlmRequestFailedException');
        } catch (LlmRequestFailedException $e) {
            $this->assertSame(503, $e->getHttpStatus());
        }
    }

    public function test_generate_throws_unavailable_on_connection_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 7: Connection refused');
        });

        $this->expectException(LlmUnavailableException::class);

        $this->client->generate('Describe vitamin C.');
    }

    public function test_generate_throws_request_failed_when_response_key_missing(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/generate" => Http::response(['unexpected' => 'shape'], 200),
        ]);

        $this->expectException(LlmRequestFailedException::class);

        $this->client->generate('Describe vitamin C.');
    }

    public function test_generate_passes_options_to_request_body(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/generate" => Http::response(['response' => 'ok'], 200),
        ]);

        $this->client->generate('prompt', ['temperature' => 0.5]);

        Http::assertSent(fn($request) => $request->data()['temperature'] === 0.5);
    }

    public function test_generate_options_can_override_model(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/generate" => Http::response(['response' => 'ok'], 200),
        ]);

        $this->client->generate('prompt', ['model' => 'llama3']);

        Http::assertSent(fn($request) => $request->data()['model'] === 'llama3');
    }

    // -------------------------------------------------------------------------
    // chat()
    // -------------------------------------------------------------------------

    public function test_chat_returns_message_content_on_success(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/chat" => Http::response([
                'message' => ['role' => 'assistant', 'content' => 'Protein builds muscle.'],
            ], 200),
        ]);

        $result = $this->client->chat([
            ['role' => 'system', 'content' => 'You are a nutritionist.'],
            ['role' => 'user',   'content' => 'What does protein do?'],
        ]);

        $this->assertSame('Protein builds muscle.', $result);
    }

    public function test_chat_throws_request_failed_on_http_error(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/chat" => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(LlmRequestFailedException::class);

        $this->client->chat([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_chat_throws_request_failed_when_message_content_missing(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/chat" => Http::response(['unexpected' => 'shape'], 200),
        ]);

        $this->expectException(LlmRequestFailedException::class);

        $this->client->chat([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_chat_throws_unavailable_on_connection_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 7: Connection refused');
        });

        $this->expectException(LlmUnavailableException::class);

        $this->client->chat([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_chat_passes_messages_and_options_to_request_body(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/chat" => Http::response([
                'message' => ['role' => 'assistant', 'content' => 'ok'],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Hello']];
        $this->client->chat($messages, ['temperature' => 0.7]);

        Http::assertSent(function ($request) use ($messages) {
            return $request->data()['messages'] === $messages
                && $request->data()['temperature'] === 0.7;
        });
    }

    // -------------------------------------------------------------------------
    // isAvailable()
    // -------------------------------------------------------------------------

    public function test_is_available_returns_true_when_api_responds(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/tags" => Http::response(['models' => []], 200),
        ]);

        $this->assertTrue($this->client->isAvailable());
    }

    public function test_is_available_returns_false_on_http_error(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/tags" => Http::response('', 500),
        ]);

        $this->assertFalse($this->client->isAvailable());
    }

    public function test_is_available_returns_false_on_connection_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $this->assertFalse($this->client->isAvailable());
    }
}
