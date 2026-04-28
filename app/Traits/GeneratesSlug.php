<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait GeneratesSlug
{
    protected static function bootGeneratesSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateUniqueSlug($model->name, $model->getTable());
            }
        });
    }

    public static function generateUniqueSlug(string $name, string $table, ?int $excludeId = null): string
    {
        $base = rtrim(substr(Str::slug($name), 0, 80), '-');
        $slug = $base;
        $counter = 2;

        while (static::slugExists($table, $slug, $excludeId)) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private static function slugExists(string $table, string $slug, ?int $excludeId): bool
    {
        $query = DB::table($table)->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
