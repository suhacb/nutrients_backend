<?php

namespace App\Console\Commands;

use App\Console\Concerns\RebuildsZincIndices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class Setup extends Command
{
    use RebuildsZincIndices;

    protected $signature = 'app:setup
                            {--force : Required when APP_ENV is not local}';

    protected $description = 'Fresh install: migrate, seed, and rebuild Zinc indices. DESTRUCTIVE — local dev only.';

    public function handle(): int
    {
        if (app()->environment() !== 'local' && !$this->option('force')) {
            $this->error('This command is destructive. Pass --force to run outside of local.');
            return self::FAILURE;
        }

        if (!$this->step('Refreshing database',     fn() => $this->refreshDatabase())) return self::FAILURE;
        if (!$this->step('Seeding database',        fn() => $this->seedDatabase()))    return self::FAILURE;
        if (!$this->step('Rebuilding Zinc indices', fn() => $this->rebuildZincIndices())) return self::FAILURE;
        if (!$this->step('Queuing seeded nutrients for sync', fn() => $this->queueSeededForSync())) return self::FAILURE;

        $this->info('Setup complete. Run app:import-from-source to load data.');
        return self::SUCCESS;
    }

    private function refreshDatabase(): bool
    {
        Artisan::call('migrate:fresh', [], $this->output);
        return true;
    }

    private function seedDatabase(): bool
    {
        Artisan::call('db:seed', [], $this->output);
        return true;
    }

    private function queueSeededForSync(): bool
    {
        Artisan::call('app:queue-pending-sync', ['--model' => 'nutrients'], $this->output);
        return true;
    }

    private function step(string $label, callable $fn): bool
    {
        $this->info("→ {$label}...");
        try {
            $result = $fn();
            if ($result) {
                $this->info("  ✓ Done.");
            }
            return $result;
        } catch (\Throwable $e) {
            $this->error("  Failed: {$e->getMessage()}");
            return false;
        }
    }
}
