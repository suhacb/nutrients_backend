<?php
namespace App\Data\USDAFoodData;

use App\Data\DataTransferObject;
use App\Models\IngredientCategory;

class UsdaCategoriesData extends DataTransferObject
{
    /**
     * Converts USDA category JSON into internal array
     */
    public function toStage(array $context = []): array
    {
        return [
            'description' => $this->raw['description']
        ];    
    }

    public function toModel(): array
    { 
        return [
            'name' => $this->get('description')
        ];
    }
}