<?php
namespace App\Data\USDAFoodData;

use App\Data\DataTransferObject;
use App\Models\IngredientCategory;
use Illuminate\Database\Eloquent\Model;

class UsdaCategoriesData extends DataTransferObject
{
    protected ?string $foodCategoryDescription;

    public function __construct(array | null $data)
    {
        parent::__construct($data);

        $this->foodCategoryDescription = $this->get('description', []);        
    }

    /**
     * Validation rules matching USDA JSON keys
     */
    protected function rules(): array
    {
        return [
            'description' => ['required', 'string']
        ];
    }

    /**
     * Converts USDA nutrient JSON into internal ingredient + pivot array
     */
    public function toArray(): array
    {
        $foodCategoryArray = [
            'name' => $this->foodCategoryDescription
        ];

        $ingredientCategory = new IngredientCategory($foodCategoryArray);
        return $ingredientCategory->toArray();
    }

    public function toModel(): Model
    {
        return new IngredientCategory($this->toArray());
    }
}