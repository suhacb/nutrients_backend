<?php

namespace Tests\Unit\Import\Sources\USDA;

use App\Import\Records\BrandRecord;
use App\Import\Sources\USDA\UsdaBrandTransformer;
use PHPUnit\Framework\TestCase;

class UsdaBrandTransformerTest extends TestCase
{
    private function makeTransformer(): UsdaBrandTransformer
    {
        return new UsdaBrandTransformer();
    }

    private function rawWithBrandName(): array
    {
        return [
            'fdcId'         => 1849686,
            'brandOwner'    => 'G. T. Japan, Inc.',
            'brandName'     => 'MAEDA-EN',
            'marketCountry' => 'United States',
            'dataType'      => 'Branded',
        ];
    }

    private function rawWithoutBrandName(): array
    {
        return [
            'fdcId'         => 1106281,
            'brandOwner'    => "MICHELE'S",
            'marketCountry' => 'United States',
            'dataType'      => 'Branded',
        ];
    }

    public function test_returns_brand_record(): void
    {
        $record = $this->makeTransformer()->transform($this->rawWithBrandName());

        $this->assertInstanceOf(BrandRecord::class, $record);
    }

    public function test_uses_brand_name_as_name_when_present(): void
    {
        $record = $this->makeTransformer()->transform($this->rawWithBrandName());

        $this->assertSame('MAEDA-EN', $record->name);
    }

    public function test_falls_back_to_brand_owner_as_name_when_brand_name_absent(): void
    {
        $record = $this->makeTransformer()->transform($this->rawWithoutBrandName());

        $this->assertSame("MICHELE'S", $record->name);
    }

    public function test_maps_brand_owner_to_owner(): void
    {
        $record = $this->makeTransformer()->transform($this->rawWithBrandName());

        $this->assertSame('G. T. Japan, Inc.', $record->owner);
    }

    public function test_maps_market_country_to_country(): void
    {
        $record = $this->makeTransformer()->transform($this->rawWithBrandName());

        $this->assertSame('United States', $record->country);
    }

    public function test_country_is_null_when_market_country_absent(): void
    {
        $raw    = $this->rawWithBrandName();
        unset($raw['marketCountry']);

        $record = $this->makeTransformer()->transform($raw);

        $this->assertNull($record->country);
    }
}
