<?php

namespace Tests\Unit\AI\Agent;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Extractor;
use App\AI\Contracts\LlmClientContract;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeExtractor(LlmClientContract $llm, array $categories = ['Health benefits'], int $maxChars = 5000): Extractor
    {
        return new Extractor(
            llm:            $llm,
            categories:     $categories,
            systemPrompt:   'Extract relevant information.',
            maxSourceChars: $maxChars,
        );
    }

    private function llmReturning(string $response): LlmClientContract
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturn($response);
        return $llm;
    }

    private function contextWithSources(string $prompt, array $sources): AgentContext
    {
        $ctx = new AgentContext($prompt);
        foreach ($sources as $i => [$url, $text]) {
            $path = "test-sources/{$i}.txt";
            Storage::put($path, $text);
            $ctx->addSource(Storage::path($path), $url, 'Title');
        }
        return $ctx;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_calls_llm_once_per_source(): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->twice()->andReturn('extracted facts');

        $ctx = $this->contextWithSources('zinc?', [
            ['https://a.com', str_repeat('a', 100)],
            ['https://b.com', str_repeat('b', 100)],
        ]);

        $this->makeExtractor($llm)->extract($ctx);

        $this->assertCount(2, $ctx->getExtractions());
    }

    public function test_categories_are_passed_to_llm(): void
    {
        $captured = null;
        $llm      = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->once()->andReturnUsing(function (array $msgs) use (&$captured) {
            $captured = $msgs;
            return 'extraction';
        });

        $ctx = $this->contextWithSources('zinc?', [['https://a.com', str_repeat('x', 100)]]);
        $this->makeExtractor($llm, ['General description', 'Health benefits'])->extract($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('General description', $allText);
        $this->assertStringContainsString('Health benefits', $allText);
    }

    public function test_source_url_is_included_in_llm_message(): void
    {
        $captured = null;
        $llm      = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->once()->andReturnUsing(function (array $msgs) use (&$captured) {
            $captured = $msgs;
            return 'extraction';
        });

        $ctx = $this->contextWithSources('zinc?', [['https://ods.nih.gov/zinc', str_repeat('x', 100)]]);
        $this->makeExtractor($llm)->extract($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('https://ods.nih.gov/zinc', $allText);
    }

    public function test_extraction_is_stored_to_disk_and_added_to_context(): void
    {
        $ctx = $this->contextWithSources('zinc?', [['https://a.com', str_repeat('x', 100)]]);
        $this->makeExtractor($this->llmReturning('Zinc is essential.'))->extract($ctx);

        $extractions = $ctx->getExtractions();
        $this->assertCount(1, $extractions);
        $this->assertSame(0, $extractions[0]['sourceIndex']);
        $this->assertSame('Zinc is essential.', file_get_contents($extractions[0]['path']));
    }

    public function test_source_text_is_truncated_to_max_source_chars_before_sending_to_llm(): void
    {
        $captured = null;
        $llm      = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->once()->andReturnUsing(function (array $msgs) use (&$captured) {
            $captured = $msgs;
            return 'extraction';
        });

        $ctx = $this->contextWithSources('zinc?', [['https://a.com', str_repeat('z', 500)]]);
        $this->makeExtractor($llm, ['Health benefits'], 20)->extract($ctx);

        $userMessage = collect($captured)->firstWhere('role', 'user')['content'];
        $this->assertStringContainsString(str_repeat('z', 20), $userMessage);
        $this->assertStringNotContainsString(str_repeat('z', 21), $userMessage);
    }

    public function test_research_question_is_included_in_llm_message(): void
    {
        $captured = null;
        $llm      = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->once()->andReturnUsing(function (array $msgs) use (&$captured) {
            $captured = $msgs;
            return 'extraction';
        });

        $ctx = $this->contextWithSources('Describe the benefits of zinc.', [['https://a.com', str_repeat('x', 100)]]);
        $this->makeExtractor($llm)->extract($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('Describe the benefits of zinc.', $allText);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_does_nothing_when_no_sources(): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');

        $ctx = new AgentContext('zinc?');
        $this->makeExtractor($llm)->extract($ctx);

        $this->assertCount(0, $ctx->getExtractions());
    }

    public function test_continues_extracting_after_single_llm_failure(): void
    {
        $callCount = 0;
        $llm       = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new \RuntimeException('LLM timeout');
            }
            return 'Good extraction';
        });

        $ctx = $this->contextWithSources('zinc?', [
            ['https://a.com', str_repeat('a', 100)],
            ['https://b.com', str_repeat('b', 100)],
        ]);
        $this->makeExtractor($llm)->extract($ctx);

        $this->assertCount(1, $ctx->getExtractions());
        $this->assertSame(1, $ctx->getExtractions()[0]['sourceIndex']);
    }
}
