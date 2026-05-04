<?php

namespace Tests\Feature\OpenApi;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OpenApiSpecTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('l5-swagger:generate');
    }

    // -------------------------------------------------------------------------
    // Endpoint availability
    // -------------------------------------------------------------------------

    public function test_spec_endpoint_returns_ok(): void
    {
        $this->get('/api/docs/openapi.json')->assertOk();
    }

    // -------------------------------------------------------------------------
    // OpenAPI structure
    // -------------------------------------------------------------------------

    public function test_spec_is_openapi_3(): void
    {
        $json = $this->get('/api/docs/openapi.json')->json();

        $this->assertStringStartsWith('3.', $json['openapi'] ?? '');
    }

    public function test_spec_has_nutrients_api_title(): void
    {
        $json = $this->get('/api/docs/openapi.json')->json();

        $this->assertSame('Nutrients API', $json['info']['title'] ?? null);
    }

    public function test_spec_version_matches_app_config(): void
    {
        $json = $this->get('/api/docs/openapi.json')->json();

        $this->assertSame(config('app.api_version'), $json['info']['version'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Paths
    // -------------------------------------------------------------------------

    public function test_spec_contains_key_resource_paths(): void
    {
        $json  = $this->get('/api/docs/openapi.json')->json();
        $paths = array_keys($json['paths'] ?? []);

        $this->assertContains('/api/nutrients', $paths);
        $this->assertContains('/api/ingredients', $paths);
        $this->assertContains('/api/auth/login', $paths);
        $this->assertContains('/api/agent', $paths);
    }

    // -------------------------------------------------------------------------
    // Security
    // -------------------------------------------------------------------------

    public function test_spec_declares_frontend_bearer_security_scheme(): void
    {
        $json = $this->get('/api/docs/openapi.json')->json();

        $this->assertArrayHasKey('frontend', $json['components']['securitySchemes'] ?? []);
        $this->assertSame('http', $json['components']['securitySchemes']['frontend']['type'] ?? null);
        $this->assertSame('bearer', $json['components']['securitySchemes']['frontend']['scheme'] ?? null);
    }
}
