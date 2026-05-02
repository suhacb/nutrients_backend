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
        'https://fdc.nal.usda.gov/',
        'https://www.nal.usda.gov/human-nutrition-and-food-safety/food-composition',
        'https://food-nutrition.canada.ca/cnf-fce/index-eng.jsp',
        'https://www.fao.org/infoods/',
        'https://world.openfoodfacts.org/',
        'https://pubmed.ncbi.nlm.nih.gov/',
        'https://www.cochranelibrary.com/',
        'https://www.who.int/health-topics/nutrition',
        'https://www.nice.org.uk/',
        'https://www.efsa.europa.eu/',
        'https://ods.od.nih.gov/',
        'https://www.ars.usda.gov/',
    ],
];