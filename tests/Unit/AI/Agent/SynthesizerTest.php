<?php

namespace Tests\Unit\AI\Agent;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Synthesizer;
use App\AI\Contracts\LlmClientContract;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SynthesizerTest extends TestCase
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

    private function llmReturning(string $response, ?\Closure $captor = null): LlmClientContract
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->once()->andReturnUsing(function (array $messages) use ($response, $captor) {
            if ($captor) {
                $captor($messages);
            }
            return $response;
        });
        return $llm;
    }

    private function contextWithExtractions(string $prompt, array $extractions): AgentContext
    {
        $ctx = new AgentContext($prompt);
        foreach ($extractions as $i => [$url, $text]) {
            $sourcePath     = "sources/{$i}.txt";
            $extractionPath = "extractions/{$i}.txt";
            Storage::put($sourcePath, 'source text');
            Storage::put($extractionPath, $text);
            $ctx->addSource(Storage::path($sourcePath), $url, 'Title');
            $ctx->addExtraction(Storage::path($extractionPath), $i);
        }
        return $ctx;
    }

    // -------------------------------------------------------------------------
    // With extractions (main path)
    // -------------------------------------------------------------------------

    public function test_returns_llm_response_when_extractions_present(): void
    {
        $ctx = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Zinc supports immunity.'],
        ]);

        $result = (new Synthesizer($this->llmReturning('Zinc supports immunity and wound healing.')))->synthesize($ctx);

        $this->assertSame('Zinc supports immunity and wound healing.', $result);
    }

    public function test_extraction_content_is_included_in_llm_message(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Zinc is critical for enzyme function.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('Zinc is critical for enzyme function.', $allText);
    }

    public function test_source_urls_are_included_in_llm_message(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://ods.nih.gov/zinc', 'Some extracted facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('https://ods.nih.gov/zinc', $allText);
    }

    public function test_original_prompt_is_included_in_llm_message(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Some facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('What does zinc do?', $allText);
    }

    public function test_extraction_categories_from_config_are_included_in_llm_message(): void
    {
        config(['ai.extraction.categories' => ['Health benefits', 'Recommended intake']]);

        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Some facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('Health benefits', $allText);
        $this->assertStringContainsString('Recommended intake', $allText);
    }

    public function test_assembles_from_multiple_extractions(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Fact from source A.'],
            ['https://b.com', 'Fact from source B.'],
        ]);

        (new Synthesizer($this->llmReturning('combined answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('Fact from source A.', $allText);
        $this->assertStringContainsString('Fact from source B.', $allText);
    }

    // -------------------------------------------------------------------------
    // System prompt — no emojis / no preamble / no disclaimers
    // -------------------------------------------------------------------------

    public function test_system_message_forbids_emojis(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Some facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $systemContent = collect($captured)->firstWhere('role', 'system')['content'];
        $this->assertStringContainsStringIgnoringCase('emoji', $systemContent);
    }

    public function test_system_message_forbids_preamble(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Some facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $systemContent = collect($captured)->firstWhere('role', 'system')['content'];
        $this->assertMatchesRegularExpression('/no.*(introduction|overview paragraph|preamble)/i', $systemContent);
    }

    public function test_system_message_forbids_disclaimers(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Some facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $systemContent = collect($captured)->firstWhere('role', 'system')['content'];
        $this->assertStringContainsStringIgnoringCase('disclaimer', $systemContent);
    }

    // -------------------------------------------------------------------------
    // User message — enforced section structure
    // -------------------------------------------------------------------------

    public function test_user_message_contains_all_canonical_section_headings(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Some facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $userContent = collect($captured)->firstWhere('role', 'user')['content'];
        foreach (config('ai.extraction.categories') as $category) {
            $this->assertStringContainsString("## {$category}", $userContent);
        }
    }

    public function test_user_message_instructs_to_start_directly_with_first_section(): void
    {
        $captured   = null;
        $firstCategory = config('ai.extraction.categories')[0];
        $ctx        = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Some facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $userContent = collect($captured)->firstWhere('role', 'user')['content'];
        $this->assertMatchesRegularExpression('/start directly with.*##\s*' . preg_quote($firstCategory, '/') . '/i', $userContent);
    }

    public function test_user_message_instructs_no_closing_remarks(): void
    {
        $captured = null;
        $ctx      = $this->contextWithExtractions('What does zinc do?', [
            ['https://a.com', 'Some facts.'],
        ]);

        (new Synthesizer($this->llmReturning('answer', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $userContent = collect($captured)->firstWhere('role', 'user')['content'];
        $this->assertMatchesRegularExpression('/no.*(closing|disclaimer)/i', $userContent);
    }

    // -------------------------------------------------------------------------
    // Fallback (no extractions)
    // -------------------------------------------------------------------------

    public function test_falls_back_to_prompt_only_when_no_extractions(): void
    {
        $captured = null;
        $ctx      = new AgentContext('What does zinc do?');

        (new Synthesizer($this->llmReturning('Fallback answer.', function ($msgs) use (&$captured) {
            $captured = $msgs;
        })))->synthesize($ctx);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('What does zinc do?', $allText);
    }

    public function test_fallback_returns_llm_response(): void
    {
        $ctx    = new AgentContext('What does zinc do?');
        $result = (new Synthesizer($this->llmReturning('Fallback answer.')))->synthesize($ctx);

        $this->assertSame('Fallback answer.', $result);
    }
}
