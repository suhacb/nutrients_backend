<?php
namespace App\Parsers\USDA;

use App\Models\Nutrient;
use App\Parsers\ParserContract;
use Illuminate\Support\Collection;

class UsdaNutrientsParser implements ParserContract
{
    protected array $data = [];
    protected Collection $parsed;

    public function setData(array $data): self
    {
        $this->data = $data;
        $this->parsed = collect();
        return $this;

    }

    public function prepare(): self
    {
        $extractedNutrients = collect();
        collect($this->data['FoundationFoods'])->each(function($item, $key) use ($extractedNutrients) {
            $food = collect($item);
            $nutrients = collect($food->get('foodNutrients') ?? []);
        
            $nutrients->each(function($item, $key) use ($extractedNutrients) {
                $extractedNutrients->push([
                    'source' => 'USDA FoodData Central',
                    'external_id' => !empty($item['nutrient']['number']) ? $item['nutrient']['number'] : null,
                    'name' => $item['nutrient']['name'],
                    'description' => null,
                    'derivation_code' => !empty($item['nutrient']['derivation_code']) ? $item['nutrient']['derivation_code'] : null,
                    'derivation_description' => !empty($item['nutrient']['derivation_description']) ? $item['nutrient']['derivation_description'] : null,
                ]);
            });
        });
        $this->parsed = $extractedNutrients
            ->unique(fn($item) => ($item['external_id'] ?? '') . '|' . ($item['name'] ?? ''))
            ->sortBy('external_id')
            ->values();
        
        return $this;
    }

    private function convertItem (array $item): Nutrient
    {
        return new Nutrient([
            'source' => $item['source'],
            'external_id' => $item['external_id'],
            'name' => $item['name'],
            'description' => $item['description'],
            'derivation_code' => $item['derivation_code'],
            'derivation_description' => $item['derivation_description'],
        ]);
    }

    public function get(): Collection
    {
        return $this->parsed;
    }

    public function convert(): self
    {
        $this->parsed = $this->parsed->map(fn($item) => $this->convertItem($item));
        return $this;
    }

    public function parse(array $data): Collection
    {
        return $this->setData($data)->prepare()->convert()->get();
    }
}