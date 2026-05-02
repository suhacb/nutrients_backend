<?php

namespace Tests\Unit\AI\Agent;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Synthesizer;
use App\AI\Contracts\LlmClientContract;
use Mockery;
use Tests\TestCase;

class SynthesizerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeLlm(string $returns, ?\Closure $captor = null): LlmClientContract
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')
            ->once()
            ->andReturnUsing(function (array $messages) use ($returns, $captor) {
                if ($captor) {
                    $captor($messages);
                }
                return $returns;
            });
        return $llm;
    }

    private function contextWithResults(string $prompt, array $toolResults = []): AgentContext
    {
        $context = new AgentContext($prompt);
        foreach ($toolResults as $entry) {
            $context->addToolResult($entry['tool'], $entry['args'], $entry['result']);
        }
        return $context;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_returns_llm_response(): void
    {
        $context = $this->contextWithResults('What does magnesium do?');

        $result = (new Synthesizer($this->makeLlm('Magnesium supports enzyme reactions.')))->synthesize($context);

        $this->assertSame('Magnesium supports enzyme reactions.', $result);
    }

    public function test_original_prompt_is_included_in_llm_prompt(): void
    {
        $captured = null;
        $context  = $this->contextWithResults('What does magnesium do?');

        (new Synthesizer($this->makeLlm('answer', function ($messages) use (&$captured) {
            $captured = $messages;
        })))->synthesize($context);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('What does magnesium do?', $allText);
    }

    public function test_tool_results_are_included_in_llm_prompt(): void
    {
        $captured = null;
        $context  = $this->contextWithResults('What does magnesium do?', [
            [
                'tool'   => 'web_search',
                'args'   => ['query' => 'magnesium benefits'],
                'result' => [['url' => 'https://example.com', 'title' => 'Magnesium', 'snippet' => 'Important mineral.']],
            ],
        ]);

        (new Synthesizer($this->makeLlm('answer', function ($messages) use (&$captured) {
            $captured = $messages;
        })))->synthesize($context);

        $allText = implode(' ', array_column($captured, 'content'));
        $this->assertStringContainsString('web_search', $allText);
        $this->assertStringContainsString('Important mineral.', $allText);
    }

    public function test_empty_tool_results_still_calls_llm(): void
    {
        $context = $this->contextWithResults('What does magnesium do?');

        $result = (new Synthesizer($this->makeLlm('Answer from knowledge.')))->synthesize($context);

        $this->assertSame('Answer from knowledge.', $result);
    }
}
