<?php
namespace App\Parsers\USDA;

use stdClass;
use App\Models\Unit;
use App\Models\Nutrient;
use App\Models\Ingredient;
use App\Parsers\ParserContract;
use Illuminate\Support\Collection;

class UsdaIngredientsParser implements ParserContract
{
    protected array $data;
    protected Collection $parsed;

    public function setData(array $data): self
    {
        $this->data = $data;
        $this->parsed = collect();
        return $this;
    }

    public function prepare(): self
    {
        $extractedIngredients = collect([]);
        
        $sourceIngredients = collect($this->data['FoundationFoods']);
        $sourceIngredients->each(function($sourceIngredient, $key) use ($extractedIngredients) {

            if (array_key_exists('foodPortions', $sourceIngredient) && sizeof($sourceIngredient['foodPortions']) > 0) {
                $defaultAmountUnitId = $sourceIngredient['foodPortions'][0]['measureUnit'];
            } else {
                $defaultAmountUnitId = Unit::where(['abbreviation' => 'g'])->first()->value('id');
            }
            logger($defaultAmountUnitId);

            $extractedIngredients->push([
                'external_id' => $sourceIngredient['ndbNumber'],
                'source' => 'USDA FoodData Central',
                'class' => $sourceIngredient['foodClass'],
                'name' => $sourceIngredient['description'],
                'description' => null,
                'default_amount' => 1,
                'default_amount_unit' => $defaultAmountUnitId,
                'nutrients' => $sourceIngredient['foodNutrients']
            ]);
        });

        $this->parsed = $extractedIngredients;

        return $this;
    }

    public function convert(): self
    {
        $this->parsed = $this->parsed->map(fn($item) => $this->convertItem($item));
        return $this;
    }

    private function convertItem (array $item): Ingredient
    {
        $externalId = $item['external_id'];
        $source = $item['source'];
        $class = $item['class'];
        $name = $item['name'];
        $defaultAmount = $item['default_amount'];
        $defaultAmountUnitId = Unit::where(['abbreviation' => $item['default_amount_unit']])->firstOrFail();
        
        $nutrients = collect([]);
        collect($item['nutrients'])->each(function($sourceNutrient, $key) use ($nutrients) {
            $nutrient = Nutrient::where([
                'external_id' => $sourceNutrient['nutrient']['number'],
                'name' => $sourceNutrient['nutrient']['name']
            ])->firstOrFail();
            if ($nutrient) {
                $nutrient->pivot = new stdClass();
                $nutrient->pivot->amount = $sourceNutrient['amount'];
                $nutrient->pivot->amount_unit_id = Unit::where('abbreviation', $sourceNutrient['nutrient']['unitName'])->value('id');
                $nutrients->push($nutrient);
            }
        });

        $ingredient = new Ingredient([
            'external_id' => $externalId,
            'source' => $source,
            'class' => $class,
            'name' => $name,
            'default_amount' => $defaultAmount,
            'default_amount_unit_id' => $defaultAmountUnitId,
        ]);

        $ingredient->setRelation('nutrients', $nutrients);

        
        return $ingredient;
    }

    public function parse (array $data): Collection
    {
        return $this->setData($data)->prepare()->convert()->get();
    }

    public function get(): Collection
    {
        return $this->parsed;
    }
}


/**
 * 1. Read the raw element
 * 2. Mappings of raw element (top level ingredient)
 *    - source foodClass maps to target class
 *    - source description maps to target name
 *    - source foodNutrients maps to Nutrient and IngredientNutrientPivot
 *    - source scientificName ignore
 *    - source foodAttributes ignore
 *    - source nutrientConversionFactors maps to UsdaNutrientConversionFactor and is used to calculate nutrition facts for energy
 *    - source isHistoricalReference ignore
 *    - source ndbNumber maps to external ID but check if it's unique
 *    - source dataType ignore
 *    - source foodCategory maps to categories
 *    - source fdcid ignore
 *    - source foodPortions ignore
 *    - source publicationDate ignore
 *    - source inputFoods ignore
 */