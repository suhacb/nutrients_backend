# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
composer test

# Run a single test file
php artisan test tests/Feature/Ingredients/IngredientsControllerTest.php

# Run a single test method
php artisan test tests/Feature/Ingredients/IngredientsControllerTest.php --filter=test_index_returns_paginated_ingredients

# Code style (Laravel Pint)
vendor/bin/pint

# Start full dev stack (server + queue + logs + npm)
composer dev

# Run migrations
php artisan migrate

# Listen to job queues
php artisan queue:listen --queue=ingredients,nutrients
```

## Architecture

This is a **Laravel 12 REST API** for managing ingredients and their nutritional data. It integrates with two external services: an **auth backend** (validates tokens) and a **Zinc search engine** (full-text search).

### Domains

- **Ingredients** — CRUD with soft deletes. Each ingredient has many `IngredientNutritionFact` records (pivot with amount/unit) and belongs to many `IngredientCategory` records.
- **Nutrients** — CRUD with soft deletes. Cannot be deleted if attached to any ingredient (throws `NutrientAttachedException`).
- **Units** — Reference data (gram, milligram, etc.) used by ingredient nutrition facts.
- **Search** — Routes hit `ZincSearchService` which implements `SearchServiceContract`. Responses are cached; cache is invalidated when query changes.

### Key Patterns

**Sync Jobs on Model Events** — When an Ingredient or Nutrient is created/updated/deleted/restored, a queued job (`SyncIngredientToSearch` / `SyncNutrientToSearch`) syncs the record to Zinc. Jobs run on separate queues: `ingredients` and `nutrients`.

**Auth Middleware** — All `/api/nutrients`, `/api/ingredients`, and `/api/search` routes are protected by `verify.frontend` (`VerifyFrontend`), which validates the access token against the external auth service and also logs in the user via `Auth::login()`. Requests must include four headers: `Authorization: Bearer <token>`, `X-Refresh-Token`, `X-Application-Name`, and `X-Client-Url`. The `ensure.user.from.token` (`EnsureUserFromToken`) middleware is only applied to the `GET /api/auth/validate-access-token` route.

**DynamicRequest** — Base class for all FormRequests. Routes validation rules and messages to method-specific implementations using controller action name: `rulesFor{Method}()` / `messagesFor{Method}()` (e.g. `rulesForStore()`, `rulesForUpdate()`).

**Service Layer** — `SearchServiceContract` is bound to `ZincSearchService` via service provider. `AuthService` and `UserService` handle cross-cutting concerns.

**Two Nutrition Data Models** — `IngredientNutritionFact` (`ingredient_nutrition_facts` table) stores flat USDA nutritional label rows (category, name, amount, unit) per ingredient. `IngredientNutrientPivot` (`ingredient_nutrient` table) is the structured many-to-many pivot between `Ingredient` and `Nutrient` models with amount/unit. Both are eager-loaded via `Ingredient::loadForSearch()` when syncing to Zinc.

**USDA Data Import** — `ImportIngredients` console command imports from USDA food data using DTO classes under `app/Data/`.

### Test Structure

Tests use three shared traits:
- `RefreshDatabase` — resets DB before each test
- `LoginTestUser` — authenticates against the external auth service (requires running auth backend)
- `MakesUnit` — helper to find or create units

Most Feature tests call `Queue::fake()` in `setUp()` to prevent actual job dispatch. The test database is `nutrients_test` (configured in `phpunit.xml`).

### External Dependencies

The app requires three external services to run:
1. **MySQL** — main data store (default DB: `nutrients`, test DB: `nutrients_test`)
2. **Auth Backend** — validates tokens; configured via `NUTRIENTS_AUTH_URL_BACKEND`
3. **Zinc Search** — full-text search; configured via `ZINC_BASE_URI`, `ZINC_USER`, `ZINC_PASSWORD`

Copy `.env.example` → `.env` and `.env.testing.example` → `.env.testing` to configure local URLs.
