<?php
namespace App\Data\USDAFoodData;

use Exception;
use App\Models\Ingredient;
use App\Data\DataTransferObject;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Data\USDAFoodData\UsdaNutrientConversionFactorType;

class UsdaNutrientConversionFactor extends DataTransferObject
{
    /**
     * Convert DTO to array (for import / export)
     */
    public function toStage(array $context = []): array
    {
        $ingredient_id = $context['ingredient_id'] ?? null;

        // Validate presence of required data
        if (empty($ingredient_id)) {
            throw new RuntimeException('Missing ingredient_id in context.');
        }

        $type = ltrim($this->get('type'), '.');

        if (strcasecmp($type, 'CalorieConversionFactor') === 0) {
            // For calorie type, map each key except 'type'
            foreach ($this->getRaw() as $key => $value) {
                if ($key === 'type') continue;
                return [
                    'ingredient_id' => $ingredient_id,
                    'conversion_factor_type' => $type,
                    'conversion_factor_category' => $key,
                    'value' => $value,
                ];
            }

        } elseif (strcasecmp($type, 'ProteinConversionFactor') === 0) {
            // For protein type, there's only a single value
            return [
                'ingredient_id' => $ingredient_id,
                'conversion_factor_type' => $type,
                'conversion_factor_category' => 'protein',
                'value' => $this->get('value'),
            ];
        } else {
            return [];
        }
    }

    public function toModel(): array
    {
        return [];

        // return [
        //     'type' => $this->type->enum, // enum as string
        //     'proteinValue' => $this->proteinValue,
        //     'fatValue' => $this->fatValue,
        //     'carbohydrateValue' => $this->carbohydrateValue,
        //     'value' => $this->value,
        //     'nitrogenValue' => $this->nitrogenValue,
        // ];
    }

    public static function fromArrayCollection(array $items): Collection
    {
        return collect($items)->map(fn($item) => static::fromArray($item));
    }
    
    public static function fromArray(array $data): static
    {
        return new static($data);
    }
}
