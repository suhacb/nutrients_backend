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
            $extractedIngredients->push([
                'external_id' => $sourceIngredient['ndbNumber'],
                'source' => 'USDA FoodData Central',
                'class' => $sourceIngredient['foodClass'],
                'name' => $sourceIngredient['description'],
                'description' => null,
                'default_amount' => 1,
                'default_amount_unit' => $sourceIngredient['foodPortions'][0]['measureUnit']['abbreviation'],
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