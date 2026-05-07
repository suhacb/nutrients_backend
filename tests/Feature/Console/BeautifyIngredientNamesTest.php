<?php

namespace Tests\Feature\Console;

use App\AI\Contracts\LlmClientContract;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\MakesUnit;
use Tests\TestCase;

class BeautifyIngredientNamesTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->unit = $this->makeUnit();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function ingredient(string $name): Ingredient
    {
        return Ingredient::withoutEvents(fn () => Ingredient::factory()->create([
            'name'                   => $name,
            'default_amount_unit_id' => $this->unit->id,
        ]));
    }

    private function mockLlm(array $beautified): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturn(json_encode($beautified));
        $this->app->instance(LlmClientContract::class, $llm);
    }

    // -------------------------------------------------------------------------
    // Default behaviour
    // -------------------------------------------------------------------------

    public function test_renames_ingredients_and_persists_to_database(): void
    {
        $a = $this->ingredient('VITAMIN C');
        $b = $this->ingredient('OLIVE OIL, EXTRA VIRGIN');

        $this->mockLlm([
            ['id' => $a->id, 'name' => 'Vitamin C'],
            ['id' => $b->id, 'name' => 'Olive Oil, Extra Virgin'],
        ]);

        $this->artisan('ingredients:beautify-names')->assertSuccessful();

        $this->assertSame('Vitamin C', $a->fresh()->name);
        $this->assertSame('Olive Oil, Extra Virgin', $b->fresh()->name);
    }

    public function test_outputs_rename_count(): void
    {
        $a = $this->ingredient('VITAMIN C');

        $this->mockLlm([['id' => $a->id, 'name' => 'Vitamin C']]);

        $this->artisan('ingredients:beautify-names')
            ->expectsOutputToContain('1')
            ->assertSuccessful();
    }

    public function test_does_nothing_when_no_ingredients(): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        $this->artisan('ingredients:beautify-names')->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // --dry-run flag
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_persist_changes(): void
    {
        $a = $this->ingredient('VITAMIN C');

        $this->mockLlm([['id' => $a->id, 'name' => 'Vitamin C']]);

        $this->artisan('ingredients:beautify-names --dry-run')->assertSuccessful();

        $this->assertSame('VITAMIN C', $a->fresh()->name);
    }

    public function test_dry_run_outputs_old_and_new_names(): void
    {
        $a = $this->ingredient('VITAMIN C');

        $this->mockLlm([['id' => $a->id, 'name' => 'Vitamin C']]);

        $this->artisan('ingredients:beautify-names --dry-run')
            ->expectsOutputToContain('VITAMIN C')
            ->expectsOutputToContain('Vitamin C')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Batching
    // -------------------------------------------------------------------------

    public function test_processes_ingredients_in_batches_of_50(): void
    {
        for ($i = 0; $i < 51; $i++) {
            $this->ingredient("INGREDIENT {$i}");
        }

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->twice()->andReturn('[]');
        $this->app->instance(LlmClientContract::class, $llm);

        $this->artisan('ingredients:beautify-names')->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // --id flag
    // -------------------------------------------------------------------------

    public function test_id_flag_processes_only_specified_ingredients(): void
    {
        $a = $this->ingredient('VITAMIN C');
        $b = $this->ingredient('OLIVE OIL');
        $c = $this->ingredient('ZINC');

        $this->mockLlm([
            ['id' => $a->id, 'name' => 'Vitamin C'],
            ['id' => $b->id, 'name' => 'Olive Oil'],
        ]);

        $this->artisan("ingredients:beautify-names --id={$a->id} --id={$b->id}")->assertSuccessful();

        $this->assertSame('Vitamin C', $a->fresh()->name);
        $this->assertSame('Olive Oil', $b->fresh()->name);
        $this->assertSame('ZINC', $c->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Soft deletes
    // -------------------------------------------------------------------------

    public function test_skips_soft_deleted_ingredients(): void
    {
        Ingredient::withoutEvents(function () {
            Ingredient::factory()->create([
                'name'                   => 'VITAMIN C',
                'default_amount_unit_id' => $this->unit->id,
            ])->delete();
        });

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        $this->artisan('ingredients:beautify-names')->assertSuccessful();
    }
}
