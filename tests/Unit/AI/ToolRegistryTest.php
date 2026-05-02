<?php

namespace Tests\Unit\AI;

use App\AI\Contracts\ToolContract;
use App\AI\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    private function makeTool(string $name, string $description = 'A tool.', array $parameters = []): ToolContract
    {
        return new class($name, $description, $parameters) implements ToolContract {
            public function __construct(
                private string $toolName,
                private string $toolDescription,
                private array  $toolParameters,
            ) {}

            public function name(): string        { return $this->toolName; }
            public function description(): string { return $this->toolDescription; }
            public function parameters(): array   { return $this->toolParameters; }
            public function run(array $args): mixed { return null; }
        };
    }

    public function test_resolve_returns_registered_tool_by_name(): void
    {
        $registry = new ToolRegistry();
        $tool     = $this->makeTool('web_search');

        $registry->register($tool);

        $this->assertSame($tool, $registry->resolve('web_search'));
    }

    public function test_resolve_throws_for_unknown_tool_name(): void
    {
        $registry = new ToolRegistry();

        $this->expectException(\InvalidArgumentException::class);

        $registry->resolve('unknown_tool');
    }

    public function test_capabilities_returns_correct_structure(): void
    {
        $registry = new ToolRegistry();
        $registry->register($this->makeTool('web_search', 'Search the web.', ['query' => 'string']));
        $registry->register($this->makeTool('web_fetch', 'Fetch a URL.', ['url' => 'string']));

        $capabilities = $registry->capabilities();

        $this->assertCount(2, $capabilities);
        $this->assertSame([
            'name'        => 'web_search',
            'description' => 'Search the web.',
            'parameters'  => ['query' => 'string'],
        ], $capabilities[0]);
        $this->assertSame([
            'name'        => 'web_fetch',
            'description' => 'Fetch a URL.',
            'parameters'  => ['url' => 'string'],
        ], $capabilities[1]);
    }

    public function test_capabilities_returns_empty_array_when_no_tools_registered(): void
    {
        $registry = new ToolRegistry();

        $this->assertSame([], $registry->capabilities());
    }
}
