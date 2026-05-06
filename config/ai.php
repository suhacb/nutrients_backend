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
            'You are a knowledgeable nutrition assistant. Answer clearly and concisely based on established nutritional science. Format your response in markdown. Do not use emojis.'
        ),
    ],

    'planner' => [
        'system_prompt' => 'You are a planning assistant for a nutrition research tool. Given a user question and a list of available tools, output a JSON array of steps to gather the information needed to answer the question. Each step must have a "tool" key (the tool name) and an "args" key (an object with the tool\'s required arguments). CRITICAL: Identify the exact entity name(s) the user mentions (e.g. "vitamin C", "olive oil", "magnesium") and use them verbatim in the tool args — never substitute, assume, or invent entity names. For web_search steps, format the query as: "{entity name} nutrition benefits and metabolism". Output ONLY valid JSON. No explanation, no markdown, no code fences.',
    ],

    'extraction' => [
        'system_prompt'    => 'You are an information extractor for a nutrition research tool. Given a source document and a research question, extract only the facts and data points relevant to answering the question. Organise by the provided categories. If a category has no relevant information in this source, omit it. Discard navigation text, boilerplate, and irrelevant content. Be concise and factual.',
        'max_source_chars' => (int) env('AI_EXTRACTION_MAX_SOURCE_CHARS', 24000),
        'categories'       => [
            'General description',
            'Role in the body and metabolism',
            'Health benefits',
            'Recommended intake and dosage',
            'Supplementation',
            'Dos and don\'ts',
        ],
    ],

    'searxng' => [
        'base_url' => env('SEARXNG_BASE_URL', 'http://localhost:8080'),
        'limit'    => (int) env('SEARXNG_RESULT_LIMIT', 10),
    ],

    'sources' => [],
];