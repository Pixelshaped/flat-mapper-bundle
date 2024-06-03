<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Pixelshaped\FlatMapperBundle\FlatMapper;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithoutConstructor;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithTooManyIdentifiers;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ColumnArrayDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\LeafDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\RootDTO as ValidRootDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTO as InvalidRootDTO;
use RuntimeException;

#[CoversMethod(FlatMapper::class, 'createMapping')]
#[CoversMethod(FlatMapper::class, 'map')]
class FlatMapperTest extends TestCase
{
    public function testCreateMappingWithValidDTOsDoesNotAssert(): void
    {
        $this->expectNotToPerformAssertions();
        $mapper = new FlatMapper();
        $mapper->createMapping(ColumnArrayDTO::class);
        $mapper->createMapping(ValidRootDTO::class);
    }

    public function testCreateMappingWithSeveralIdenticalIdentifiersAsserts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/Several data identifiers are identical/");
        $mapper = new FlatMapper();
        $mapper->createMapping(InvalidRootDTO::class);
    }

    public function testCreateMappingWithTooManyIdentifiersAsserts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/contains more than one #\[Identifier\] attribute/");
        $mapper = new FlatMapper();
        $mapper->createMapping(RootDTOWithTooManyIdentifiers::class);
    }

    public function testCreateMappingWithNoConstructorAsserts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/does not have a constructor/");
        $mapper = new FlatMapper();
        $mapper->createMapping(RootDTOWithoutConstructor::class);
    }

    public function testMapValidNestedDTOs(): void
    {
        $results = [
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 1, 'object2_name' => 'Leaf 1', 'object2_value' => 'Value 1'],
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 2, 'object2_name' => 'Leaf 2', 'object2_value' => 'Value 2'],
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 3, 'object2_name' => 'Leaf 3', 'object2_value' => 'Value 3'],
            ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 1, 'object2_name' => 'Leaf 1', 'object2_value' => 'Value 1'],
            ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 4, 'object2_name' => 'Leaf 4', 'object2_value' => 'Value 4'],
        ];

        $flatMapperResults = ((new FlatMapper())->map(ValidRootDTO::class, $results));

        $leafDto1 = new LeafDTO(1, "Leaf 1", "Value 1");
        $leafDto2 = new LeafDTO(2, "Leaf 2", "Value 2");
        $leafDto3 = new LeafDTO(3, "Leaf 3", "Value 3");
        $leafDto4 = new LeafDTO(4, "Leaf 4", "Value 4");

        $rootDto1 = new ValidRootDTO(1, "Root 1", [
            1 => $leafDto1, 2 => $leafDto2, 3 => $leafDto3
        ]);
        $rootDto2 = new ValidRootDTO(2, "Root 2", [
            1 => $leafDto1, 4 => $leafDto4
        ]);
        $handmadeResult = [1 => $rootDto1, 2 => $rootDto2];

        $this->assertSame(
            var_export($flatMapperResults, true),
            var_export($handmadeResult, true)
        );
    }

    public function testMapValidColumnArrayDTO(): void
    {
        $results = [
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 1],
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 2],
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 3],
            ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 1],
            ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 4],
        ];

        $flatMapperResults = ((new FlatMapper())->map(ColumnArrayDTO::class, $results));

        $rootDto1 = new ColumnArrayDTO(1, "Root 1", [1, 2, 3]);
        $rootDto2 = new ColumnArrayDTO(2, "Root 2", [1, 4]);
        $handmadeResult = [1 => $rootDto1, 2 => $rootDto2];

        $this->assertSame(
            var_export($flatMapperResults, true),
            var_export($handmadeResult, true)
        );
    }
}