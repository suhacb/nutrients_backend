# Data Quality Improvements — Specification

## Overview

Six improvements to the data quality of ingredients and nutrients. Items are ordered by implementation priority.

---

## 1. Nutrient Description Quality and Structure

**Goal:** Descriptions must read like encyclopedia entries — factual, structured, free of preamble, disclaimers, and emojis.

### Problems

- The synthesizer sometimes opens with an introductory paragraph ("In this article we will explore...") before the actual content.
- Disclaimers appear at the end ("Consult a healthcare professional before...").
- Emojis appear despite the existing `Do not use emojis` instruction (the instruction is in the user message, not the system prompt, so it is weaker).
- Section headings are inconsistent: sometimes bold text (`**Health Benefits**`), sometimes markdown H2 (`## Health Benefits`), sometimes missing altogether.
- The set of sections is not always complete — some descriptions omit sections that have no data rather than noting their absence.

### Changes

**`config/ai.php` — `agent.system_prompt`**

Replace the current value with a stricter instruction that bans emojis, preamble, and disclaimers at the system level:

```
You are a nutrition encyclopedia editor. Write factual, well-structured entries based on established nutritional science. Use markdown. Never use emojis. Never open with an introduction or overview paragraph. Never close with a disclaimer or recommendation to consult a professional. Get straight to the content.
```

**`config/ai.php` — `extraction.categories`**

Define the canonical section list (order matters — synthesizer must use this order):

```php
'categories' => [
    'Overview',
    'Role in the body and metabolism',
    'Health benefits',
    'Recommended intake and dosage',
    'Supplementation',
    'Interactions and contraindications',
],
```

Note: "General description" is renamed to "Overview" (cleaner heading). "Dos and don'ts" is replaced by "Interactions and contraindications" (encyclopedic tone). One new section is added to replace the vague dos/don'ts.

**`app/AI/Agent/Synthesizer.php` — user message**

Replace the freeform "write a comprehensive answer" instruction with a strict structure mandate:

```
Question: {prompt}

Using only the research extractions below, write a nutrient encyclopedia entry.

Structure the entry with exactly these markdown H2 sections, in this order:
## Overview
## Role in the body and metabolism
## Health benefits
## Recommended intake and dosage
## Supplementation
## Interactions and contraindications

Rules:
- Start directly with ## Overview — no introduction before it.
- If a section has no data from the sources, write "Data not available." under the heading rather than omitting the section.
- End after ## Interactions and contraindications — no closing remarks, no disclaimers.
- No emojis.

Research extractions from {n} sources:

{extractions}
```

**New command: `app/Console/Commands/RegenerateNutrientDescriptions.php`**

A command to re-run description generation for nutrients whose descriptions were generated under the old prompt. Options:

- `--all` — regenerate all nutrients that have a non-null description.
- `--ids=1,2,3` — regenerate specific nutrient IDs.
- `--source=usda` — regenerate all nutrients from a given source slug.

Dispatches `GenerateNutrientDescription` jobs in the same way the existing `GenerateNutrientDescriptions` command does.

**`app/Jobs/GenerateNutrientDescription.php` — prompt**

Update `buildPrompt()` to align with the new categories:

```
Research {name} as a nutrient. Cover: overview, role in the body and metabolism, health benefits, recommended intake and dosage, supplementation, and interactions and contraindications.
```

---

## 2. Ingredient Name Beautification

**Goal:** Convert raw USDA all-caps ingredient names (e.g. `MCDONALD'S, EGG MCMUFFIN`) into properly capitalised human-readable names (e.g. `McDonald's, Egg McMuffin`).

### Approach

Pass ingredient names through the LLM with a tightly constrained prompt. The LLM handles edge cases that rule-based title-casing cannot: brand names, abbreviations, Roman numerals, proper nouns.

### Changes

**New command: `app/Console/Commands/BeautifyIngredientNames.php`**

- Fetches ingredients whose names match a heuristic for all-caps: `WHERE name REGEXP '^[A-Z0-9 ,\'\(\)\-\.%]+$'` (all characters are uppercase letters, digits, or punctuation).
- Processes in batches of 50 (configurable via `--batch=N`).
- Per batch: sends all names in a single LLM request with a structured prompt (see below).
- Parses the response and updates each ingredient name if the returned name differs.
- Logs each rename as `ingredient.beautify` with `id`, `old`, `new`.
- Options:
  - `--dry-run` — log intended changes but do not write to DB.
  - `--batch=50` — batch size.
  - `--ids=1,2,3` — process specific ingredient IDs only.

**LLM prompt (per batch)**

```
You are a text formatter. You will receive a JSON array of food ingredient names in all-caps format.
For each name, return the correctly capitalised version as it would appear on a product label or in a food database.
Rules:
- Preserve brand names, trademarks, and proper nouns (e.g. McDonald's, KFC, NutraSweet).
- Use title case for generic food terms (e.g. "WHOLE MILK" -> "Whole Milk").
- Preserve abbreviations and codes (e.g. "NFS", "UPC").
- Do not change numbers, percentages, or units.
- Return only a JSON array of strings in the same order as the input. No explanation.

Input: ["MCDONALD'S, EGG MCMUFFIN", "WHOLE MILK, 3.25% FAT", "VITAMIN C"]
```

**`app/Import/Sources/USDA/UsdaIngredientTransformer.php`**

No change to the transformer itself — beautification runs as a post-import batch command, not inline. This keeps the import pipeline fast and the beautification auditable.

---

## 3. Nutrient Parent Classification

**Goal:** USDA-imported nutrients are created with `parent_id = null`. Each should be linked to the closest matching node in the system-seeded hierarchy.

### System hierarchy (from seeder)

```
Macronutrients
  Energy, Protein, Water
  Fat
    Saturated Fat, Trans Fat, Cholesterol
    Unsaturated Fat
      Monounsaturated Fat, Polyunsaturated Fat
  Carbohydrates
    Dietary Fiber, Sugars
Micronutrients
  Vitamins
    Fat-soluble Vitamins, Water-soluble Vitamins
  Minerals
    Macrominerals, Trace Minerals
Fatty Acids
  Omega-3 Fatty Acids, Omega-6 Fatty Acids, Omega-9 Fatty Acids
Amino Acids
  Essential Amino Acids, Non-essential Amino Acids
```

### Approach

Load the full system hierarchy and pass it as context to the LLM. For each unclassified USDA nutrient, ask the LLM to return the `id` of the most specific matching parent node. Use a structured JSON response to avoid parsing ambiguity.

### Changes

**New command: `app/Console/Commands/ClassifyNutrientParents.php`**

1. Loads all system-source nutrients (where `source.slug = 'system'`) with their `id`, `name`, and `parent_id`, and builds a labelled tree string for the prompt context.
2. Queries USDA nutrients with `parent_id IS NULL` (where `source.slug = 'usda'`).
3. Processes in batches of 20.
4. Per batch: sends a single LLM request with all nutrient names and the full hierarchy, asking for a JSON object mapping each nutrient name to a parent ID.
5. Validates each returned ID is a real system nutrient ID before writing.
6. Updates `parent_id` on matched nutrients.
7. Logs each assignment as `nutrient.classify` with `id`, `name`, `assigned_parent_id`, `parent_name`.
8. Options:
   - `--dry-run` — log intended assignments without writing.
   - `--batch=20` — batch size.
   - `--confidence-only` — only assign if the LLM returns a match (skip nutrients the LLM returns `null` for).

**LLM prompt (per batch)**

```
You are a nutrition taxonomy assistant. Below is a nutrient hierarchy with node IDs.
For each nutrient in the INPUT list, return the ID of the most specific node in the hierarchy that the nutrient belongs to.
If no node fits, return null for that nutrient.
Return only a JSON object mapping each nutrient name to a node ID or null. No explanation.

HIERARCHY:
{hierarchy tree with IDs}

INPUT:
["Thiamin", "Niacin", "18:2 n-6 c,c", "22:6 n-3", "Alanine", ...]
```

**Hierarchy string format (example)**

```
1: Macronutrients
  5: Energy
  6: Protein
  ...
2: Micronutrients
  9: Vitamins
    11: Fat-soluble Vitamins
    12: Water-soluble Vitamins
  10: Minerals
    13: Macrominerals
    14: Trace Minerals
...
```

---

## 4. PDF Source Chunking

**Goal:** Long PDF documents are currently truncated at `max_source_chars` (default 24 000 chars). Chunking allows the full document to be processed.

### Approach

Split source text into overlapping windows. Each chunk is stored as a separate source file and processed by the Extractor independently. The Synthesizer receives extractions from all chunks.

### Changes

**`config/ai.php` — new keys under `extraction`**

```php
'chunk_size'    => (int) env('AI_EXTRACTION_CHUNK_SIZE', 24000),
'chunk_overlap' => (int) env('AI_EXTRACTION_CHUNK_OVERLAP', 2000),
'max_chunks'    => (int) env('AI_EXTRACTION_MAX_CHUNKS', 5),
```

`max_chunks` caps the total LLM calls per source to prevent runaway costs on very long documents.

**`app/AI/Agent/Gatherer.php`**

Replace the current single-file store logic with a `chunkAndStore` method:

- If `mb_strlen($text) <= chunk_size`, behave as today (single file, no change in behaviour for short sources).
- Otherwise: split into overlapping chunks of `chunk_size` with `chunk_overlap` leading overlap on each chunk after the first. Store each chunk as `{i}_{chunkIndex}.txt`. Add each as a separate source entry in the context.
- Stop after `max_chunks` chunks per source.
- Remove the old `mb_substr` truncation.

**`app/AI/Agent/AgentContext.php`**

The `addSource` / `getSources` interface does not need to change — each chunk is just an additional source entry with the same URL.

---

## 5. PHP Memory Limit

No change required. The PHP-FPM container (`docker-compose/nutrients_backend/php/Dockerfile`, line 30) already sets `memory_limit=512M`.

---

## 6. Alternative Model Experiment (Gemma)

**Goal:** Allow description generation to be run with an alternative Ollama model (e.g. `gemma4:26b`) for comparison without changing the default configuration.

### Changes

**`app/Console/Commands/GenerateNutrientDescriptions.php`**

Add a `--model` option. When provided, temporarily override `config('ai.ollama.model')` for the duration of the command:

```php
$this->option('model') && config(['ai.ollama.model' => $this->option('model')]);
```

This affects all jobs dispatched synchronously. For queued jobs the config change does not propagate — if `--model` is used, jobs should run synchronously (`--sync` flag, see below) or the model should be stored on the job.

Add a `--sync` flag that runs jobs synchronously (via `dispatchSync`) instead of queuing them. This is useful for small experiments where you want to see output immediately and compare models.

**`app/Jobs/GenerateNutrientDescription.php`**

Add an optional `$model` constructor parameter. When non-null, set `config(['ai.ollama.model' => $this->model])` at the start of `handle()` before the orchestrator runs.
