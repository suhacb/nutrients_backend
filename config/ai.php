<?php

return [
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model'    => env('OLLAMA_MODEL', 'gemma4:e4b'),
        'timeout'  => (int) env('OLLAMA_TIMEOUT', 60),
    ],

    'sources' => [
        // Trusted nutrition sources used by web search tools (populated in ai-tools branch)
    ],
];