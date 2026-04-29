<?php

namespace Tests\Feature\Import;

use App\Jobs\SyncSourceToSearch;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Tests\MakesUnit;
use Tests\TestCase;

class LinkBrandsFromUsdaTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    protected string $fixture;
    protected string $backupPath;
    protected Source $source;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->fixture    = base_path('tests/fixtures/branded_food_sample.json');
        $this->backupPath = sys_get_temp_dir() . '/nutrients-test-backup-' . uniqid() . '.sql';

        $this->source = Source::factory()->create([
            'slug' => 'usda-food-data-central',
            'name' => 'USDA FoodData Central',
        ]);

        $this->makeUnit();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->backupPath)) {
            unlink($this->backupPath);
        }
        parent::tearDown();
    }

    private function artisanLink(array $overrides = []): PendingCommand
    {
        return $this->artisan('app:link-brands-from-usda', array_merge([
            'file'     => $this->fixture,
            '--backup' => $this->backupPath,
        ], $overrides));
    }

    private function makeIngredient(string $externalId): Ingredient
    {
        return Ingredient::factory()->create([
            'external_id' => $externalId,
            'source'      => $this->source->name,
        ]);
    }

    public function test_creates_brands_and_links_to_existing_ingredients(): void
    {
        $this->makeIngredient('1106281');
        $this->makeIngredient('1849686');

        $this->artisanLink()->assertExitCode(0);

        $this->assertDatabaseHas('brands', ['name' => "MICHELE'S GRANOLA"]);
        $this->assertDatabaseHas('brands', ['name' => 'MAEDA-EN']);

        $ingredient = Ingredient::where('external_id', '1106281')->first();
        $this->assertNotNull($ingredient->brand_id);
        $this->assertEquals("MICHELE'S GRANOLA", Brand::find($ingredient->brand_id)->name);
    }

    public function test_falls_back_to_brand_owner_when_brand_name_absent(): void
    {
        $this->makeIngredient('9999999');

        $this->artisanLink()->assertExitCode(0);

        $ingredient = Ingredient::where('external_id', '9999999')->first();
        $this->assertNotNull($ingredient->brand_id);
        $this->assertEquals('GENERIC FOODS INC.', Brand::find($ingredient->brand_id)->name);
    }

    public function test_skips_ingredients_not_in_database(): void
    {
        $this->artisanLink()->assertExitCode(0);

        $this->assertDatabaseCount('brands', 3);
        $this->assertDatabaseCount('ingredients', 0);
    }

    public function test_is_idempotent(): void
    {
        $this->makeIngredient('1106281');

        $this->artisanLink()->assertExitCode(0);
        $this->artisanLink()->assertExitCode(0);

        $this->assertDatabaseCount('brands', 3);

        $ingredient = Ingredient::where('external_id', '1106281')->first();
        $this->assertNotNull($ingredient->brand_id);
    }

    public function test_dispatches_sync_source_to_search_after_linking(): void
    {
        $this->artisanLink()->assertExitCode(0);

        Queue::assertPushed(SyncSourceToSearch::class, function ($job) {
            return $job->source->is($this->source);
        });
    }

    public function test_fails_without_backup_option(): void
    {
        $this->artisan('app:link-brands-from-usda', ['file' => $this->fixture])
            ->assertExitCode(1);
    }

    public function test_fails_when_file_not_found(): void
    {
        $this->artisan('app:link-brands-from-usda', [
            'file'     => '/nonexistent/path/branded_food.json',
            '--backup' => $this->backupPath,
        ])->assertExitCode(1);
    }
}
