<?php

return [
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model'    => env('OLLAMA_MODEL', 'gemma4:e4b'),
        'timeout'  => (int) env('OLLAMA_TIMEOUT', 60),
    ],

    'agent' => [
        'system_prompt' => env(
            'AI_AGENT_SYSTEM_PROMPT',
            'You are a knowledgeable nutrition assistant. Answer clearly and concisely based on established nutritional science.'
        ),
    ],

    'searxng' => [
        'base_url' => env('SEARXNG_BASE_URL', 'http://localhost:8080'),
        'limit'    => (int) env('SEARXNG_RESULT_LIMIT', 10),
    ],

    'sources' => [
        // Trusted nutrition sources used by web search tools (populated in ai-tools branch)
    ],
];