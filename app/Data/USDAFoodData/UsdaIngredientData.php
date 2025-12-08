<?php
namespace App\Data\USDAFoodData;

use App\Models\Unit;
use App\Models\Ingredient;
use App\Data\DataTransferObject;
use Illuminate\Database\Eloquent\Model;
use App\Data\USDAFoodData\UsdaIngredientNutrientPivotData;
use Illuminate\Support\Str;

class UsdaIngredientData extends DataTransferObject
{
    protected ?string $foodClass;
    protected ?string $description;
    protected ?string $ndbNumber;
    protected ?array $foodNutrients;
    protected ?array $foodCategory;

    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->foodClass = $this->get('foodClass');
        $this->description = $this->get('description');
        $this->ndbNumber = $this->get('ndbNumber');
        $this->foodNutrients = $this->get('foodNutrients', []);
        $this->foodCategory = $this->get('foodCategory');
    }

    /**
     * Validation rules matching USDA JSON keys
     */
    protected function rules(): array
    {
        return [
            'ndbNumber' => ['required', 'numeric'],
            'foodClass' => ['required', 'string'],
            'description' => ['required', 'string'],
            'foodNutrients' => ['required', 'array'],
            'foodCategory' => ['required', 'array'],
        ];
    }

    /**
     * Converts USDA ingredient JSON into internal ingredient + pivot array
     */
    public function toArray(): array
    {
        $ingredientArray = [
            'source' => 'USDA FoodData Central',
            'external_id' => $this->ndbNumber,
            'name' => Str::limit($this->description, 255, ''),
            'description' => null,
            'class' => $this->foodClass,
            'default_amount' => 100,
            'default_amount_unit_id' => $this->getUnitId('g'),
        ];

        $nutrientsArray = [];
        $nutrientsPivotArray = [];

        foreach($this->foodNutrients as $sourceNutrient) {
            $nutrientsArrayElement = new UsdaNutrientData($sourceNutrient);
            $nutrientsArrayElement = $nutrientsArrayElement->toArray();
            $nutrientsArray[] = $nutrientsArrayElement;
            $nutrientsPivotArrayElement = new UsdaIngredientNutrientPivotData($sourceNutrient);
            $nutrientsPivotArrayElement = $nutrientsPivotArrayElement->toArray();
            $nutrientsPivotArray[] = $nutrientsPivotArrayElement;
        }

        $ingredientCategory = new UsdaCategoriesData($this->foodCategory);

        // Nutrition facts conversion
        $nutritionFactsData = new UsdaIngredientNutritionFactData($this->foodNutrients);
        $nutritionFacts = $nutritionFactsData->toArray();

        return [
            'ingredient' => $ingredientArray,
            'nutrients' => $nutrientsArray,
            'nutrients_pivot' => $nutrientsPivotArray,
            'category' => $ingredientCategory->toArray(),
            'nutrition_facts' => $nutritionFacts
        ];
    }

    public function toModel(): Model
    {
        return new Ingredient();
    }

    protected function getUnitId(string $abbreviation): int
    {
        $unit = Unit::where('abbreviation', $abbreviation)->first();

        if (!$unit) {
            logger()->error("Unit {$abbreviation} not found in database!");
            throw new \Exception("Unit {$abbreviation} not found!");
        }
        return $unit->id;
    }
}