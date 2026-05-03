<?php

namespace Tests\Unit\AI\Agent;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Planner;
use App\AI\Contracts\LlmClientContract;
use App\AI\ToolRegistry;
use App\AI\Contracts\ToolContract;
use Mockery;
use Tests\TestCase;

class PlannerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeRegistry(array $capabilities = []): ToolRegistry
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('capabilities')->andReturn($capabilities);
        return $registry;
    }

    private function makeLlm(string $returns): LlmClientContract
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->once()->andReturn($returns);
        return $llm;
    }

    private function validPlanJson(): string
    {
        return json_encode([
            ['tool' => 'web_search', 'args' => ['query' => 'magnesium benefits']],
            ['tool' => 'web_fetch',  'args' => ['url'   => 'https://example.com']],
        ]);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_valid_json_plan_is_set_on_context(): void
    {
        $planner = new Planner($this->makeLlm($this->validPlanJson()), $this->makeRegistry());
        $context = new AgentContext('What does magnesium do?');

        $planner->plan($context);

        $this->assertCount(2, $context->getPlan());
        $this->assertSame('web_search', $context->getPlan()[0]['tool']);
        $this->assertSame('web_fetch',  $context->getPlan()[1]['tool']);
    }

    public function test_json_wrapped_in_markdown_fences_is_parsed(): void
    {
        $wrapped = "```json\n" . $this->validPlanJson() . "\n```";
        $planner = new Planner($this->makeLlm($wrapped), $this->makeRegistry());
        $context = new AgentContext('What does magnesium do?');

        $planner->plan($context);

        $this->assertCount(2, $context->getPlan());
    }

    // -------------------------------------------------------------------------
    // Fallback to empty plan
    // -------------------------------------------------------------------------

    public function test_unparseable_json_sets_empty_plan(): void
    {
        $planner = new Planner($this->makeLlm('not valid json at all'), $this->makeRegistry());
        $context = new AgentContext('prompt');

        $planner->plan($context);

        $this->assertSame([], $context->getPlan());
    }

    public function test_wrong_structure_sets_empty_plan(): void
    {
        $planner = new Planner($this->makeLlm('{"tool":"web_search"}'), $this->makeRegistry());
        $context = new AgentContext('prompt');

        $planner->plan($context);

        $this->assertSame([], $context->getPlan());
    }

    public function test_empty_array_sets_empty_plan(): void
    {
        $planner = new Planner($this->makeLlm('[]'), $this->makeRegistry());
        $context = new AgentContext('prompt');

        $planner->plan($context);

        $this->assertSame([], $context->getPlan());
    }

    // -------------------------------------------------------------------------
    // Prompt composition
    // -------------------------------------------------------------------------

    public function test_capabilities_are_included_in_llm_prompt(): void
    {
        $capabilities = [
            ['name' => 'web_search', 'description' => 'Search the web.', 'parameters' => ['query' => 'string']],
        ];

        $capturedMessages = null;

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')
            ->once()
            ->andReturnUsing(function (array $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return $this->validPlanJson();
            });

        $planner = new Planner($llm, $this->makeRegistry($capabilities));
        $planner->plan(new AgentContext('What does magnesium do?'));

        $allText = implode(' ', array_column($capturedMessages, 'content'));
        $this->assertStringContainsString('web_search', $allText);
    }

    public function test_system_prompt_instructs_verbatim_entity_extraction(): void
    {
        $captured = null;

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')
            ->once()
            ->andReturnUsing(function (array $messages) use (&$captured) {
                $captured = $messages;
                return $this->validPlanJson();
            });

        (new Planner($llm, $this->makeRegistry()))->plan(new AgentContext('Describe vitamin C'));

        $system = collect($captured)->firstWhere('role', 'system')['content'] ?? '';
        $this->assertStringContainsString('verbatim', strtolower($system));
    }

    public function test_user_prompt_is_forwarded_in_user_message(): void
    {
        $captured = null;

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')
            ->once()
            ->andReturnUsing(function (array $messages) use (&$captured) {
                $captured = $messages;
                return $this->validPlanJson();
            });

        (new Planner($llm, $this->makeRegistry()))->plan(new AgentContext('Describe vitamin C'));

        $user = collect($captured)->firstWhere('role', 'user')['content'] ?? '';
        $this->assertStringContainsString('Describe vitamin C', $user);
    }
}
