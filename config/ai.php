<?php

return [
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model'    => env('OLLAMA_MODEL', 'gemma4:e4b'),
        'timeout'  => (int) env('OLLAMA_TIMEOUT', 300),
        'models'   => [
            'fast'  => env('OLLAMA_MODEL_FAST', 'gemma4:e4b'),
            'smart' => env('OLLAMA_MODEL_SMART', 'gemma4:26b'),
        ],
    ],

    'agent' => [
        'system_prompt' => env(
            'AI_AGENT_SYSTEM_PROMPT',
            'You are a knowledgeable nutrition assistant. Answer clearly and concisely based on established nutritional science. Format your response in markdown. Do not use emojis. Do not include a preamble, no introduction paragraph, no overview paragraph before the first section. Do not add disclaimers or closing remarks.'
        ),
        // Synthesis with sources: input = all extractions (≈1 500 tok each × max_chunks) + overhead.
        // Reduce AI_EXTRACTION_MAX_CHUNKS to 3 together with AI_SYNTHESIS_NUM_CTX = 8192
        // if VRAM is tight; the default (5 sources, 16 384 ctx) gives the best answer quality.
        'llm_options' => ['options' => [
            'num_ctx'     => (int) env('AI_SYNTHESIS_NUM_CTX', 16384),
            'num_predict' => (int) env('AI_SYNTHESIS_NUM_PREDICT', 4096),
        ]],
        // Fallback (no sources fetched): direct answer; input is tiny
        'llm_fallback_options' => ['options' => ['num_ctx' => 4096, 'num_predict' => 2048]],
    ],

    'planner' => [
        'system_prompt' => 'You are a planning assistant for a nutrition research tool. Given a user question and a list of available tools, output a JSON array of steps to gather the information needed to answer the question. Each step must have a "tool" key (the tool name) and an "args" key (an object with the tool\'s required arguments). CRITICAL: Identify the exact entity name(s) the user mentions (e.g. "vitamin C", "olive oil", "magnesium") and use them verbatim in the tool args — never substitute, assume, or invent entity names. For web_search steps, derive the most relevant search query from the user\'s question — use the exact entity name(s) and select keywords that best reflect what information is being sought. Output ONLY valid JSON. No explanation, no markdown, no code fences.',

        'fetch_system_prompt' => 'You are a URL selector for a nutrition research tool. Given a research question and search results (each with a URL, title, and snippet), select the most relevant and authoritative URLs to fetch — at most 5. For each selected URL, determine the correct fetch tool: use "pdf_fetch" if the URL path ends with ".pdf", otherwise use "web_fetch". Output ONLY a valid JSON array. Each element must have a "tool" key ("web_fetch" or "pdf_fetch") and an "args" key with {"url": "<selected_url>"}. No explanation, no markdown, no code fences.',

        // Input: tool list + question ≈ 500 tokens; output: small JSON plan
        'llm_options' => ['options' => ['num_ctx' => 4096, 'num_predict' => 512, 'temperature' => 0.1]],
        // Input: search snippets + question ≈ 900 tokens; output: at most 5 URL objects
        'llm_fetch_options' => ['options' => ['num_ctx' => 2048, 'num_predict' => 256, 'temperature' => 0.1]],
    ],

    'extraction' => [
        'system_prompt'    => 'You are an information extractor for a nutrition research tool. Given a source document and a research question, extract only the facts and data points relevant to answering the question. Organise by the provided categories. If a category has no relevant information in this source, omit it. Discard navigation text, boilerplate, and irrelevant content. Be concise and factual.',
        'max_source_chars' => (int) env('AI_EXTRACTION_MAX_SOURCE_CHARS', 24000),
        'chunk_overlap'    => (int) env('AI_EXTRACTION_CHUNK_OVERLAP', 2000),
        'max_chunks'       => (int) env('AI_EXTRACTION_MAX_CHUNKS', 5),
        'max_pdf_bytes'    => (int) env('AI_EXTRACTION_MAX_PDF_BYTES', 10 * 1024 * 1024),
        // 24 000 chars ≈ 6 000 tokens of source; num_ctx must cover input + output.
        // Reduce AI_EXTRACTION_MAX_SOURCE_CHARS to 16 000 together with num_ctx = 8192
        // if VRAM is tight; increase both for higher quality on capable hardware.
        'llm_options' => ['options' => [
            'num_ctx'     => (int) env('AI_EXTRACTION_NUM_CTX', 10240),
            'num_predict' => (int) env('AI_EXTRACTION_NUM_PREDICT', 2048),
        ]],
        'categories'       => [
            'Overview',
            'Role in the body and metabolism',
            'Health benefits',
            'Recommended intake and dosage',
            'Supplementation',
            'Interactions and contraindications',
        ],
    ],

    'ingredient_description' => [
        'categories' => [
            'Culinary overview',
            'Nutritional profile',
            'Health benefits',
            'Culinary uses',
            'Diet compatibility',
        ],
        'diets' => [
            'keto',
            'LCHF',
            'carnivore',
            'paleo',
            'whole30',
            'anti-inflammatory',
            'Mediterranean',
            'DASH',
            'plant-based (flexitarian)',
            'vegetarian',
            'vegan',
            'high protein',
            'gluten-free',
            'low-FODMAP',
        ],
    ],

    'searxng' => [
        'base_url' => env('SEARXNG_BASE_URL', 'http://localhost:8080'),
        'limit'    => (int) env('SEARXNG_RESULT_LIMIT', 10),
        'timeout'  => (int) env('SEARXNG_TIMEOUT', 60),
    ],

    'sources' => [],
];