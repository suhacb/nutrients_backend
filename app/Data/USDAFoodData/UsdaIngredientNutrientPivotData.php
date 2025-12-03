<?php
namespace App\Data\USDAFoodData;

use App\Models\Unit;
use App\Data\DataTransferObject;
use App\Models\IngredientNutrientPivot;
use Illuminate\Database\Eloquent\Model;

class UsdaIngredientNutrientPivotData extends DataTransferObject
{
    protected ?string $unitName;
    protected ?float $amount;

    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->unitName = $this->get('nutrient.unitName');
        $this->amount = $this->get('amount');
    }

    /**
     * Validation rules matching USDA JSON keys
     */
    protected function rules(): array
    {
        return [
            'nutrient.unitName' => ['required', 'numeric'],
            'amount' => ['required', 'string'],
        ];
    }

    /**
     * Converts USDA nutrient JSON into internal ingredient + pivot array
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'amount_unit_id' => Unit::where(['abbreviation' => $this->unitName])->first()->id,
            'portion_amount' => null,
            'portion_amount_unit_id' => null
        ];

    }

    public function toModel(): Model
    {
        return new IngredientNutrientPivot();
    }
}