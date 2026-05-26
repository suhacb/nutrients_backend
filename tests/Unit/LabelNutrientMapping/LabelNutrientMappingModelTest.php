<?php

namespace Tests\Unit\LabelNutrientMapping;

use App\Models\LabelNutrientMapping;
use App\Models\Nutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelNutrientMappingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $fillable = (new LabelNutrientMapping())->getFillable();

        $this->assertEqualsCanonicalizing(
            ['label_key', 'nutrient_id', 'confidence', 'reasoning', 'status'],
            $fillable
        );
    }

    public function test_model_can_be_created_with_fillable_attributes(): void
    {
        $nutrient = Nutrient::factory()->create(['is_canonical' => true]);

        $mapping = LabelNutrientMapping::create([
            'label_key'   => 'protein',
            'nutrient_id' => $nutrient->id,
            'confidence'  => 97,
            'reasoning'   => 'Protein is the direct canonical match.',
            'status'      => 'approved',
        ]);

        $this->assertDatabaseHas('label_nutrient_mappings', [
            'label_key'   => 'protein',
            'nutrient_id' => $nutrient->id,
            'status'      => 'approved',
        ]);
    }

    public function test_belongs_to_nutrient(): void
    {
        $nutrient = Nutrient::factory()->create(['is_canonical' => true]);

        $mapping = LabelNutrientMapping::create([
            'label_key'   => 'fat',
            'nutrient_id' => $nutrient->id,
            'confidence'  => 95,
            'reasoning'   => 'Total fat.',
            'status'      => 'approved',
        ]);

        $this->assertInstanceOf(Nutrient::class, $mapping->nutrient);
        $this->assertSame($nutrient->id, $mapping->nutrient->id);
    }

    public function test_confidence_is_cast_to_integer(): void
    {
        $nutrient = Nutrient::factory()->create(['is_canonical' => true]);

        $mapping = LabelNutrientMapping::create([
            'label_key'   => 'sodium',
            'nutrient_id' => $nutrient->id,
            'confidence'  => 88,
            'reasoning'   => 'Likely match.',
            'status'      => 'pending',
        ]);

        $this->assertIsInt($mapping->confidence);
        $this->assertSame(88, $mapping->confidence);
    }

    public function test_scope_approved_returns_only_approved_mappings(): void
    {
        $nutrient = Nutrient::factory()->create(['is_canonical' => true]);

        LabelNutrientMapping::create(['label_key' => 'protein', 'nutrient_id' => $nutrient->id, 'confidence' => 97, 'reasoning' => 'Match.', 'status' => 'approved']);
        LabelNutrientMapping::create(['label_key' => 'fat',     'nutrient_id' => $nutrient->id, 'confidence' => 80, 'reasoning' => 'Maybe.', 'status' => 'pending']);

        $approved = LabelNutrientMapping::approved()->get();

        $this->assertCount(1, $approved);
        $this->assertSame('protein', $approved->first()->label_key);
    }
}
