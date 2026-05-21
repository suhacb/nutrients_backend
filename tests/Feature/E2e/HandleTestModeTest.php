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

    public function test_mutations_persist_in_test_mode(): void
    {
        $this->withHeaders($this->testHeaders())
            ->postJson(route('ingredients.store'), $this->ingredientPayload)
            ->assertCreated();

        $this->assertDatabaseHas('ingredients', ['name' => 'E2E HandleTestMode Ingredient']);
    }

    public function test_mutations_persist_regardless_of_x_test_mode_header(): void
    {
        // X-Test-Mode header is no longer special — mutations always persist so that
        // E2E flows (create → navigate → view/edit) work correctly across requests.
        $this->withHeaders(array_merge($this->testHeaders(), ['X-Test-Mode' => 'true']))
            ->postJson(route('ingredients.store'), $this->ingredientPayload)
            ->assertCreated();

        $this->assertDatabaseHas('ingredients', ['name' => 'E2E HandleTestMode Ingredient']);
    }

    public function test_returns_401_when_test_mode_disabled(): void
    {
        config(['app.test_mode' => false]);

        $this->withHeaders($this->testHeaders())
            ->postJson(route('ingredients.store'), $this->ingredientPayload)
            ->assertUnauthorized();
    }
}
