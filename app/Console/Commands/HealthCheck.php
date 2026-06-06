<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthCheck extends Command
{
    protected $signature = 'app:health-check
                            {--only= : Comma-separated list of services to check: mysql, zinc, ollama, searxng, auth, qdrant}';

    protected $description = 'Check reachability of configured external services. Use php artisan --env=testing app:health-check to target .env.testing.';

    private bool $allHealthy = true;

    public function handle(): int
    {
        $only = $this->parseOnly();

        $checks = [
            'mysql'   => fn() => $this->checkMysql(),
            'zinc'    => fn() => $this->checkZinc(),
            'ollama'  => fn() => $this->checkOllama(),
            'searxng' => fn() => $this->checkSearxng(),
            // 'auth'   => fn() => $this->checkAuth(),   // uncomment when /health endpoint is implemented
            // 'qdrant' => fn() => $this->checkQdrant(), // uncomment when Qdrant is provisioned
        ];

        $rows = [];
        foreach ($checks as $key => $fn) {
            if (empty($only) || in_array($key, $only, true)) {
                $rows[] = $fn();
            }
        }

        $this->table(['Service', 'Status', 'Detail'], $rows);

        return $this->allHealthy ? self::SUCCESS : self::FAILURE;
    }

    private function parseOnly(): array
    {
        $raw = $this->option('only') ?? '';
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function checkMysql(): array
    {
        $host = config('database.connections.mysql.host', '?');
        $port = config('database.connections.mysql.port', '?');
        try {
            DB::connection()->getPdo();
            return ['MySQL', '✓ OK', "{$host}:{$port}"];
        } catch (\Throwable $e) {
            $this->allHealthy = false;
            return ['MySQL', '✗ FAIL', $e->getMessage()];
        }
    }

    private function checkZinc(): array
    {
        $url = config('zinc.base_url', '');
        if (!$url) {
            return ['ZincSearch', '— SKIP', 'ZINC_BASE_URI not set'];
        }

        return $this->httpCheck(
            'ZincSearch',
            "{$url}/healthz",
            fn(PendingRequest $r) => $r->withBasicAuth(config('zinc.username'), config('zinc.password'))
        );
    }

    private function checkOllama(): array
    {
        $url = config('ai.ollama.base_url', '');
        if (!$url) {
            return ['Ollama', '— SKIP', 'OLLAMA_BASE_URL not set'];
        }

        return $this->httpCheck('Ollama', "{$url}/api/tags");
    }

    private function checkSearxng(): array
    {
        $url = config('ai.searxng.base_url', '');
        if (!$url) {
            return ['SearxNG', '— SKIP', 'SEARXNG_BASE_URL not set'];
        }

        return $this->httpCheck('SearxNG', $url);
    }

    private function checkAuth(): array
    {
        $host = config('nutrients.auth.url_backend', '');
        $port = config('nutrients.auth.port_backend', '');
        if (!$host) {
            return ['Auth Backend', '— SKIP', 'AUTH_BACKEND_URL not set'];
        }

        $base = $port ? "{$host}:{$port}" : $host;
        return $this->httpCheck('Auth Backend', "{$base}/health");
    }

    private function checkQdrant(): array
    {
        $url = config('services.qdrant.base_url', '');
        if (!$url) {
            return ['Qdrant', '— SKIP', 'QDRANT_BASE_URL not set'];
        }

        return $this->httpCheck('Qdrant', "{$url}/readyz");
    }

    private function httpCheck(string $label, string $url, ?callable $configure = null): array
    {
        try {
            $pending = Http::timeout(5);
            if ($configure !== null) {
                $pending = $configure($pending);
            }
            $response = $pending->get($url);
            if ($response->successful()) {
                return [$label, '✓ OK', $url];
            }
            $this->allHealthy = false;
            return [$label, '✗ FAIL', "HTTP {$response->status()} — {$url}"];
        } catch (\Throwable $e) {
            $this->allHealthy = false;
            return [$label, '✗ FAIL', $e->getMessage()];
        }
    }
}
