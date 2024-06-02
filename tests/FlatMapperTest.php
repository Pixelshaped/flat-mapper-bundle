<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Pixelshaped\FlatMapperBundle\FlatMapper;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ColumnArrayDTO;
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

    public function testCreateMappingWithInvalidDTOsAsserts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/Several data identifiers are identical/");
        $mapper = new FlatMapper();
        $mapper->createMapping(InvalidRootDTO::class);
    }

    public function testMapValidObject(): void
    {
        $results = [
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 1, 'object2_name' => 'Leaf 1', 'object2_value' => 'Value 1'],
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 2, 'object2_name' => 'Leaf 2', 'object2_value' => 'Value 2'],
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 3, 'object2_name' => 'Leaf 3', 'object2_value' => 'Value 3'],
            ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 1, 'object2_name' => 'Leaf 1', 'object2_value' => 'Value 1'],
            ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 4, 'object2_name' => 'Leaf 4', 'object2_value' => 'Value 4'],
        ];

        $mappedResults = ((new FlatMapper())->map(ValidRootDTO::class, $results));

        $this->assertSame(count($mappedResults), 2);


    }
}