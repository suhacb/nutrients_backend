<?php

namespace Tests\Unit\Nutrients;

use App\Models\Nutrient;
use PHPUnit\Framework\TestCase;

class NutrientModelTest extends TestCase
{
    public function test_fillable_fields(): void
    {
        $expectedFillable = [
            'source',
            'external_id',
            'name',
            'description',
            'derivation_code',
            'derivation_description',
        ];

        $model = new Nutrient();

        $this->assertEquals(
            $expectedFillable,
            $model->getFillable(),
            'The $fillable fields do not match the expected ones'
        );
    }

    public function test_casts_fields(): void
    {
        $expectedCasts = [
            'id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];

        $model = new Nutrient();

        $this->assertEquals(
            $expectedCasts,
            $model->getCasts(),
            'The $casts fields do not match the expected ones'
        );
    }
}
