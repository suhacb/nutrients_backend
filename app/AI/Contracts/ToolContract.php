<?php

namespace App\AI\Contracts; 

interface ToolContract {
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function run(array $args): mixed;
}