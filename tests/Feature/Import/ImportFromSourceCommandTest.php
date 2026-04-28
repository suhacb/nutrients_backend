<?php

namespace Tests\Feature\Import;

use App\Jobs\SyncSourceToSearch;
use App\Models\Source;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class ImportFromSourceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $fixture;
    protected string $backupPath;
    protected Source $source;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->fixture    = base_path('tests/fixtures/usda_sample.json');
        $this->backupPath = sys_get_temp_dir() . '/nutrients-test-backup-' . uniqid() . '.sql';

        $this->source = Source::factory()->create([
            'slug' => 'usda-food-data-central',
            'name' => 'USDA FoodData Central',
        ]);

        Unit::create(['name' => 'gram',        'abbreviation' => 'g',    'type' => 'mass']);
        Unit::create(['name' => 'milligram',   'abbreviation' => 'mg',   'type' => 'mass']);
        Unit::create(['name' => 'kilocalorie', 'abbreviation' => 'kcal', 'type' => 'energy']);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->backupPath)) {
            unlink($this->backupPath);
        }
        parent::tearDown();
    }

    private function artisanImport(array $overrides = []): PendingCommand
    {
        return $this->artisan('app:import-from-source', array_merge([
            'source'   => 'usda',
            'file'     => $this->fixture,
            '--backup' => $this->backupPath,
        ], $overrides));
    }

    public function test_command_imports_ingredients_and_nutrients_from_usda_fixture(): void
    {
        $this->artisanImport()->assertExitCode(0);

        $this->assertDatabaseHas('ingredients', ['external_id' => '321358', 'name' => 'Hummus, commercial']);
        $this->assertDatabaseHas('ingredients', ['external_id' => '171705', 'name' => 'Whole Milk']);
        $this->assertDatabaseHas('nutrients',   ['external_id' => '203',    'name' => 'Protein']);
        $this->assertDatabaseCount('ingredients', 2);
        $this->assertDatabaseCount('nutrients', 2);
    }

    public function test_command_creates_database_backup_before_importing(): void
    {
        $this->artisanImport()->assertExitCode(0);

        $this->assertFileExists($this->backupPath);
    }

    public function test_command_dispatches_sync_jobs_after_import(): void
    {
        $this->artisanImport()->assertExitCode(0);

        Queue::assertPushed(SyncSourceToSearch::class);
    }

    public function test_command_is_idempotent(): void
    {
        $this->artisanImport()->assertExitCode(0);
        $this->artisanImport()->assertExitCode(0);

        $this->assertDatabaseCount('ingredients', 2);
        $this->assertDatabaseCount('nutrients', 2);
        $this->assertDatabaseCount('ingredient_nutrient', 3);
    }

    public function test_command_fails_without_backup_option(): void
    {
        $this->artisan('app:import-from-source', [
            'source' => 'usda',
            'file'   => $this->fixture,
        ])->assertExitCode(1);
    }

    public function test_command_fails_when_file_not_found(): void
    {
        $this->artisanImport(['file' => '/nonexistent/file.json'])
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($this->backupPath);
    }

    public function test_command_fails_when_source_name_is_unknown(): void
    {
        $this->artisanImport(['source' => 'unknown-source'])
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($this->backupPath);
    }

    public function test_command_fails_when_source_is_not_seeded(): void
    {
        $this->source->delete();

        $this->artisanImport()->assertExitCode(1);
    }
}
