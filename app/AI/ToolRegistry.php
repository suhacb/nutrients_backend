<?php

namespace App\AI;

use App\AI\Contracts\ToolContract;

class ToolRegistry
{
    /** @var array<string, ToolContract> */
    private array $tools = [];

    public function register(ToolContract $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function resolve(string $name): ToolContract
    {
        if (!isset($this->tools[$name])) {
            throw new \InvalidArgumentException("Tool '{$name}' is not registered.");
        }

        return $this->tools[$name];
    }

    public function capabilities(): array
    {
        return array_values(array_map(
            fn (ToolContract $tool) => [
                'name'        => $tool->name(),
                'description' => $tool->description(),
                'parameters'  => $tool->parameters(),
            ],
            $this->tools
        ));
    }
}
