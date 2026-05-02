# Branch: `ai-tools`

## Overview

This branch introduces the tool layer that the description enrichment pipeline will use. Each tool is a focused, independently testable class behind a common contract. The branch delivers `ToolContract`, `WebSearchTool` (backed by SearXNG), `WebFetchTool` (backed by `symfony/dom-crawler`), and `ToolRegistry`. `ContentQualityAssessor` is out of scope for this branch.

---

## Goals

- Define `ToolContract` as the interface all tools implement
- Implement `WebSearchTool` — queries a self-hosted SearXNG instance and returns a ranked list of URLs with titles and snippets
- Implement `WebFetchTool` — fetches a URL and extracts clean plain text using `symfony/dom-crawler`, targeting meaningful content and ignoring navigation, ads, and boilerplate
- Implement `ToolRegistry` — registers available tools and exposes their metadata for use by the `Planner` in `ai-agent-core`
- Extend `config/ai.php` with SearXNG configuration
- Full unit test coverage (TDD); no feature tests needed for this branch

---

## Dependencies

Add via Composer:

```bash
composer require symfony/dom-crawler symfony/css-selector
```

`symfony/css-selector` is required by `dom-crawler` to support CSS selector syntax (e.g. `article p`, `main p`).

---

## File Structure

```
app/
  AI/
    Contracts/
      ToolContract.php
    Tools/
      WebSearchTool.php
      WebFetchTool.php
    ToolRegistry.php

config/
  ai.php                      (extend with searxng section)

tests/
  Unit/
    AI/
      Tools/
        WebSearchToolTest.php
        WebFetchToolTest.php
      ToolRegistryTest.php
```

---

## Environment Variables

Add to `.env` and `.env.example`:

```dotenv
SEARXNG_BASE_URL=http://localhost:8080
SEARXNG_RESULT_LIMIT=10
```

---

## `config/ai.php` additions

```php
'searxng' => [
    'base_url' => env('SEARXNG_BASE_URL', 'http://localhost:8080'),
    'limit'    => (int) env('SEARXNG_RESULT_LIMIT', 10),
],
```

---

## `ToolContract`

All tools implement this interface. Designed so the `Planner` can introspect available capabilities and the `Executor` can call any tool uniformly.

```php
namespace App\AI\Contracts;

interface ToolContract
{
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function run(array $args): mixed;
}
```

### Method responsibilities

| Method | Purpose |
|---|---|
| `name()` | Machine-readable identifier (e.g. `web_search`) used in plans |
| `description()` | Human/LLM-readable description sent to Gemma during planning |
| `parameters()` | Array describing accepted arguments — used to build the capability list |
| `run(array $args)` | Executes the tool with the given arguments and returns a result |

---

## `WebSearchTool`

Queries a self-hosted SearXNG instance via its JSON API and returns the top N results.

### SearXNG JSON API

```
GET {SEARXNG_BASE_URL}/search?q={query}&format=json
```

Response shape (relevant fields):

```json
{
  "results": [
    {
      "url":     "https://ods.od.nih.gov/factsheets/Magnesium/",
      "title":   "Magnesium - Health Professional Fact Sheet",
      "content": "Magnesium is a cofactor in more than 300 enzyme systems..."
    }
  ]
}
```

### Tool metadata

```php
public function name(): string        => 'web_search'
public function description(): string => 'Searches trusted nutrition sources for a given query and returns a list of relevant URLs with titles and snippets.'
public function parameters(): array   => ['query' => 'string']
```

### `run()` return value

Returns an array of result objects, limited to `config('ai.searxng.limit')`:

```php
[
    [
        'url'     => 'https://ods.od.nih.gov/factsheets/Magnesium/',
        'title'   => 'Magnesium - Health Professional Fact Sheet',
        'snippet' => 'Magnesium is a cofactor in more than 300 enzyme systems...',
    ],
    // ...
]
```

Returns an empty array when SearXNG returns no results.

### Error handling

- Connection failure → throw `LlmUnavailableException` (reused — represents any external service being unreachable)
- HTTP error from SearXNG → throw `\RuntimeException` with status code
- Missing `results` key in response → return empty array (SearXNG may return this for zero results)

### Constructor

```php
public function __construct(
    private readonly string $baseUrl,
    private readonly int    $limit,
) {}
```

Resolved via `ToolRegistry` using `config('ai.searxng')`.

---

## `WebFetchTool`

Fetches a URL and extracts meaningful plain text using `symfony/dom-crawler`.

### Tool metadata

```php
public function name(): string        => 'web_fetch'
public function description(): string => 'Fetches a web page and returns its main textual content, stripped of navigation, scripts, and boilerplate.'
public function parameters(): array   => ['url' => 'string']
```

### `run()` return value

Returns a plain text string — the extracted content. Returns an empty string if no meaningful content is found.

### Content extraction strategy

Applied in order — first match wins:

1. All `<p>` tags inside `<article>`
2. All `<p>` tags inside `<main>`
3. All `<p>` tags in the document body

Before extraction, remove `<script>`, `<style>`, `<nav>`, `<header>`, and `<footer>` nodes entirely.

After extraction, normalise whitespace: collapse multiple spaces and blank lines into single ones.

### Error handling

- Connection failure / timeout → throw `\RuntimeException`
- HTTP error (4xx, 5xx) → throw `\RuntimeException` with status code
- Valid response but no extractable content → return empty string (caller decides how to handle)

### Constructor

```php
public function __construct(
    private readonly int $timeout = 15,
) {}
```

---

## `ToolRegistry`

Maintains a map of available tools and exposes them for both the `Executor` (run by name) and the `Planner` (introspect capabilities).

### Interface

```php
public function register(ToolContract $tool): void;
public function resolve(string $name): ToolContract;
public function capabilities(): array;
```

### `capabilities()` return value

Used by `Planner` to build the prompt for Gemma:

```php
[
    [
        'name'        => 'web_search',
        'description' => 'Searches trusted nutrition sources...',
        'parameters'  => ['query' => 'string'],
    ],
    [
        'name'        => 'web_fetch',
        'description' => 'Fetches a web page...',
        'parameters'  => ['url' => 'string'],
    ],
]
```

### Error handling

- `resolve()` called with unknown tool name → throw `\InvalidArgumentException`

### Registration

Tools are registered in `AiServiceProvider`:

```php
$registry = new ToolRegistry();
$registry->register(new WebSearchTool(
    baseUrl: config('ai.searxng.base_url'),
    limit:   config('ai.searxng.limit'),
));
$registry->register(new WebFetchTool());

$this->app->singleton(ToolRegistry::class, fn() => $registry);
```

---

## Implementation Steps (TDD order)

### Step 1 — `ToolContract`
1. Create `ToolContract` (no test needed — it is an interface)

### Step 2 — Config
1. Add `searxng` section to `config/ai.php`
2. Add env vars to `.env` and `.env.example`
3. Write config test asserting `ai.searxng.base_url` is a non-empty string and `ai.searxng.limit` is a positive integer

### Step 3 — `WebSearchTool`
1. Write failing test: valid SearXNG response → assert correct array structure returned
2. Write failing test: result count is limited to configured limit
3. Write failing test: empty results from SearXNG → assert empty array returned
4. Write failing test: missing `results` key in response → assert empty array returned
5. Write failing test: HTTP error → assert `\RuntimeException` thrown
6. Write failing test: connection failure → assert `LlmUnavailableException` thrown
7. Write failing test: `name()`, `description()`, `parameters()` return expected values
8. Implement `WebSearchTool`

### Step 4 — `WebFetchTool`
1. Write failing test: page with `<article><p>` content → assert paragraphs extracted
2. Write failing test: page with `<main><p>` content (no article) → assert paragraphs extracted
3. Write failing test: page with only body `<p>` tags → assert paragraphs extracted
4. Write failing test: `<script>`, `<nav>`, `<footer>` content is excluded from output
5. Write failing test: page with no extractable content → assert empty string returned
6. Write failing test: HTTP error → assert `\RuntimeException` thrown
7. Write failing test: connection failure → assert `\RuntimeException` thrown
8. Write failing test: `name()`, `description()`, `parameters()` return expected values
9. Implement `WebFetchTool`

### Step 5 — `ToolRegistry`
1. Write failing test: `register()` + `resolve()` by name returns correct tool instance
2. Write failing test: `resolve()` with unknown name throws `\InvalidArgumentException`
3. Write failing test: `capabilities()` returns correct structure for all registered tools
4. Write failing test: `capabilities()` returns empty array when no tools registered
5. Implement `ToolRegistry`

### Step 6 — `AiServiceProvider`
1. Write failing test: `ToolRegistry` resolves from container as singleton
2. Write failing test: resolved registry has `web_search` and `web_fetch` tools registered
3. Update `AiServiceProvider` to register tools

---

## Unit Test Outline

### `WebSearchToolTest`
```
test_returns_array_of_results_on_success
test_result_count_is_limited_to_configured_limit
test_returns_empty_array_when_no_results
test_returns_empty_array_when_results_key_missing
test_throws_runtime_exception_on_http_error
test_throws_llm_unavailable_exception_on_connection_failure
test_name_returns_web_search
test_description_returns_non_empty_string
test_parameters_returns_query_key
```

### `WebFetchToolTest`
```
test_extracts_paragraphs_from_article_element
test_extracts_paragraphs_from_main_element_when_no_article
test_extracts_paragraphs_from_body_when_no_article_or_main
test_excludes_script_nav_and_footer_content
test_returns_empty_string_when_no_extractable_content
test_throws_runtime_exception_on_http_error
test_throws_runtime_exception_on_connection_failure
test_name_returns_web_fetch
test_description_returns_non_empty_string
test_parameters_returns_url_key
```

### `ToolRegistryTest`
```
test_resolve_returns_registered_tool_by_name
test_resolve_throws_for_unknown_tool_name
test_capabilities_returns_correct_structure
test_capabilities_returns_empty_array_when_no_tools_registered
```

### `AiServiceProviderTest` (additions)
```
test_tool_registry_resolves_from_container
test_tool_registry_is_singleton
test_registry_has_web_search_tool_registered
test_registry_has_web_fetch_tool_registered
```

---

## What this branch does NOT include

- `ContentQualityAssessor` — added in a later branch
- Any agent loop, planning, or orchestration — that is `ai-agent-core`
- Any pipeline or artisan command — that is `ai-description-pipeline`
- Any changes to the `/api/agent` endpoint
