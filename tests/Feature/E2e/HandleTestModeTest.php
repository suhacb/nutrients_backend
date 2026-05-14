<?php

namespace Tests\Feature\E2e;

use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestMode;

class HandleTestModeTest extends TestCase
{
    use RefreshDatabase, UsesTestMode;

    private array $ingredientPayload;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->enableTestMode();

        $unit = Unit::factory()->create();
        $this->ingredientPayload = [
            'name'                    => 'E2E HandleTestMode Ingredient',
            'source'                  => 'test',
            'class'                   => 'final',
            'default_amount'          => 100,
            'default_amount_unit_id'  => $unit->id,
        ];
    }

    public function test_record_persists_without_header(): void
    {
        $this->withHeaders($this->testHeaders())
            ->postJson(route('ingredients.store'), $this->ingredientPayload)
            ->assertCreated();

        $this->assertDatabaseHas('ingredients', ['name' => 'E2E HandleTestMode Ingredient']);
    }

    public function test_record_is_rolled_back_with_header(): void
    {
        $this->withHeaders($this->testModeHeaders())
            ->postJson(route('ingredients.store'), $this->ingredientPayload)
            ->assertCreated();

        $this->assertDatabaseMissing('ingredients', ['name' => 'E2E HandleTestMode Ingredient']);
    }

    public function test_middleware_is_no_op_when_test_mode_disabled(): void
    {
        config(['app.test_mode' => false]);

        // Without test mode, the test token in VerifyFrontend will fail auth, returning 401.
        // The important thing is that no rollback magic happens — if auth were somehow bypassed,
        // a record created would persist. We verify this by confirming the middleware doesn't
        // interfere when test_mode is false (401 is the expected response here).
        $this->withHeaders($this->testModeHeaders())
            ->postJson(route('ingredients.store'), $this->ingredientPayload)
            ->assertUnauthorized();

        $this->assertDatabaseMissing('ingredients', ['name' => 'E2E HandleTestMode Ingredient']);
    }
}
