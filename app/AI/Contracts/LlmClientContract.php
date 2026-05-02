<?php

namespace App\AI\Contracts;

interface LlmClientContract
{
    /**
     * Send a single user prompt and return the model's text response.
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Send a structured conversation and return the model's text response.
     * Each message must have 'role' (system|user|assistant) and 'content' keys.
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Return true if the LLM service is reachable, false otherwise.
     */
    public function isAvailable(): bool;
}