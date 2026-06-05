<?php

namespace App\Console\Concerns;

use Illuminate\Support\Facades\Http;

trait RebuildsZincIndices
{
    protected function rebuildZincIndices(): bool
    {
        $baseUri  = config('zinc.base_url');
        $user     = config('zinc.username');
        $password = config('zinc.password');

        foreach (config('zinc.indices') as $key => $name) {
            $this->line("  Deleting index: {$name}");
            Http::withBasicAuth($user, $password)->delete("{$baseUri}/api/index/{$name}");

            $this->line("  Creating index: {$name}");
            $response = Http::withBasicAuth($user, $password)->put("{$baseUri}/api/index", array_merge(
                ['name' => $name],
                config("zinc.index_definitions.{$key}", [])
            ));

            if (!$response->successful()) {
                $this->error("Failed to create Zinc index '{$name}': {$response->body()}");
                return false;
            }
        }

        return true;
    }
}
