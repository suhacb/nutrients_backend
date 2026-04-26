<?php

namespace Tests\Unit\Import;

use App\Import\Contracts\ImportSourceContract;
use App\Import\Records\ImportBatch;
use PHPUnit\Framework\TestCase;

class ImportSourceContractTest extends TestCase
{
    public function test_is_an_interface(): void
    {
        $reflection = new \ReflectionClass(ImportSourceContract::class);

        $this->assertTrue($reflection->isInterface());
    }

    public function test_declares_get_source_method(): void
    {
        $reflection = new \ReflectionClass(ImportSourceContract::class);

        $this->assertTrue($reflection->hasMethod('getSource'));

        $method = $reflection->getMethod('getSource');
        $this->assertCount(0, $method->getParameters());
        $this->assertSame('App\Models\Source', $method->getReturnType()->getName());
    }

    public function test_declares_stream_method(): void
    {
        $reflection = new \ReflectionClass(ImportSourceContract::class);

        $this->assertTrue($reflection->hasMethod('stream'));

        $method = $reflection->getMethod('stream');
        $this->assertCount(1, $method->getParameters());
        $this->assertSame('file', $method->getParameters()[0]->getName());
        $this->assertSame('string', $method->getParameters()[0]->getType()->getName());
        $this->assertSame('Generator', $method->getReturnType()->getName());
    }

    public function test_declares_transform_method(): void
    {
        $reflection = new \ReflectionClass(ImportSourceContract::class);

        $this->assertTrue($reflection->hasMethod('transform'));

        $method = $reflection->getMethod('transform');
        $this->assertCount(1, $method->getParameters());
        $this->assertSame('raw', $method->getParameters()[0]->getName());
        $this->assertSame('array', $method->getParameters()[0]->getType()->getName());
        $this->assertSame(ImportBatch::class, $method->getReturnType()->getName());
    }

    public function test_concrete_class_can_implement_contract(): void
    {
        $implementation = new class implements ImportSourceContract {
            public function getSource(): \App\Models\Source
            {
                return new \App\Models\Source();
            }

            public function stream(string $file): \Generator
            {
                yield [];
            }

            public function transform(array $raw): ImportBatch
            {
                return new ImportBatch(
                    ingredient:          new \App\Import\Records\IngredientRecord('1', 'Test', null, null, null, null),
                    category:            new \App\Import\Records\IngredientCategoryRecord('Test'),
                    nutrients:           [new \App\Import\Records\NutrientRecord('1', 'Test', null, null)],
                    ingredientNutrients: [],
                    nutritionFacts:      [],
                );
            }
        };

        $this->assertInstanceOf(ImportSourceContract::class, $implementation);
    }
}
