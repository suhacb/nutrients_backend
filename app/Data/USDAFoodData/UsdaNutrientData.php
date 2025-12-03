<?php
namespace App\Data\USDAFoodData;

use App\Models\Unit;
use App\Models\Nutrient;
use App\Data\DataTransferObject;
use Illuminate\Database\Eloquent\Model;

class UsdaNutrientData extends DataTransferObject
{
    protected array $nutrient;
    protected ?float $amount;
    protected ?float $median;
    protected ?string $unitName;
    protected ?string $derivationCode;
    protected ?string $derivationDescription;

    /**
     * Override parent constructor to hydrate properties
     */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->nutrient = $this->get('nutrient', []);
        $this->amount = $this->get('amount');
        $this->median = $this->get('median');
        $this->unitName = $this->get('nutrient.unitName');
        $this->derivationCode = $this->get('foodNutrientDerivation.code');
        $this->derivationDescription = $this->get('foodNutrientDerivation.description');
    }

    /**
     * Validation rules matching USDA JSON keys
     */
    protected function rules(): array
    {
        return [
            'nutrient.id' => ['required', 'integer'],
            'nutrient.number' => ['required', 'string'],
            'nutrient.name' => ['required', 'string'],
            'nutrient.unitName' => ['required', 'string'],
            'amount' => ['nullable', 'numeric'],
            'median' => ['nullable', 'numeric'],
        ];
    }

    /**
     * Converts USDA nutrient JSON into internal nutrient
     */
    public function toArray(): array
    {
        return [
            'source' => 'USDA FoodData Central',
            'external_id' => $this->nutrient['number'],
            'name' => $this->nutrient['name'],
            'description' => null,
            'derivation_code' => $this->derivationCode,
            'derivation_description' => $this->derivationDescription,
        ];
    }

    /**
     * Create an Eloquent Nutrient model (not persisted)
     */
    public function toModel(): Model
    {
        return new Nutrient($this->toArray());
    }

    /**
     * Creates a pivot-ready array for ingredient_nutrient table
     * Optionally resolves Unit model by name.
     */
    public function toPivotArray(): array
    {
        $unit = Unit::where('name', $this->unitName)->orWhere('abbreviation', $this->unitName)->first();

        return [
            'amount' => $this->median ?? $this->amount,
            'amount_unit_id' => $unit?->id,
            'portion_amount' => null,
            'portion_amount_unit_id' => null,
        ];
    }
}