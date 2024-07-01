<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Pixelshaped\FlatMapperBundle\FlatMapper;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTO as InvalidRootDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithEmptyClassIdentifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithNoIdentifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithoutConstructor;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithTooManyIdentifiers;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ColumnArray\ColumnArrayDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Complex\CustomerDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Complex\InvoiceDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Complex\ProductDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ReferencesArray\LeafDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ReferencesArray\RootDTO as ValidRootDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\WithoutAttributeDTO;
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
        $this->expectExceptionMessageMatches("/does not contain exactly one #\[Identifier\] attribute/");
        $mapper = new FlatMapper();
        $mapper->createMapping(RootDTOWithTooManyIdentifiers::class);
    }

    public function testCreateMappingWithNoIdentifierAsserts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/does not contain exactly one #\[Identifier\] attribute/");
        $mapper = new FlatMapper();
        $mapper->createMapping(RootDTOWithNoIdentifier::class);
    }

    public function testCreateMappingWithNoConstructorAsserts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/does not have a constructor/");
        $mapper = new FlatMapper();
        $mapper->createMapping(RootDTOWithoutConstructor::class);
    }

    public function testCreateMappingWithEmptyClassIdentifierAsserts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/The Identifier attribute cannot be used without a property name when used as a Class attribute/");
        $mapper = new FlatMapper();
        $mapper->createMapping(RootDTOWithEmptyClassIdentifier::class);
    }

    public function testMapValidNestedDTOs(): void
    {
        $results = [
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 1, 'object2_name' => 'Leaf 1', 'object2_value' => 'Value 1'],
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 2, 'object2_name' => 'Leaf 2', 'object2_value' => 'Value 2'],
            ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 3, 'object2_name' => 'Leaf 3', 'object2_value' => 'Value 3'],
            ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 1, 'object2_name' => 'Leaf 1', 'object2_value' => 'Value 1'],
            ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 4, 'object2_name' => 'Leaf 4', 'object2_value' => 'Value 4'],
            ['object1_id' => 5, 'object1_name' => 'Root 5', 'object2_id' => null, 'object2_name' => null, 'object2_value' => null],
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
        $rootDto5 = new ValidRootDTO(5, "Root 5", []);
        $handmadeResult = [1 => $rootDto1, 2 => $rootDto2, 5 => $rootDto5];

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
            ['object1_id' => 7, 'object1_name' => 'Root 7', 'object2_id' => null],
        ];

        $flatMapperResults = ((new FlatMapper())->map(ColumnArrayDTO::class, $results));

        $rootDto1 = new ColumnArrayDTO(1, "Root 1", [1, 2, 3]);
        $rootDto2 = new ColumnArrayDTO(2, "Root 2", [1, 4]);
        $rootDto7 = new ColumnArrayDTO(7, "Root 7", []);
        $handmadeResult = [1 => $rootDto1, 2 => $rootDto2, 7 => $rootDto7];

        $this->assertSame(
            var_export($flatMapperResults, true),
            var_export($handmadeResult, true)
        );
    }

    public function testDeepNestedDTOs(): void
    {
        // In this example you can see we do not use the product_sku as the Identifier.
        // Indeed, an identifier would only be created once, but we need quantity to be per product per invoice!
        // Therefore, we use a CONCAT of invoice_id and product_sku as an Identifier to let FlatMapper create
        // as many ProductDTO instances as needed
        $results = [
            ['customer_id' => 12, 'customer_name' => 'Bob', 'invoice_id' => 134, 'invoice_vat' => 'ABC123', 'invoice_address' => '12 Test St. Functown', 'product_id' => '134_5312','product_sku' => 5312, 'product_weight' => 1.5, 'product_price' => 120, 'product_quantity' => 5],
            ['customer_id' => 12, 'customer_name' => 'Bob', 'invoice_id' => 134, 'invoice_vat' => 'ABC123', 'invoice_address' => '12 Test St. Functown', 'product_id' => '134_645','product_sku' => 645, 'product_weight' => 12.7, 'product_price' => 350, 'product_quantity' => 2],
            ['customer_id' => 12, 'customer_name' => 'Bob', 'invoice_id' => 134, 'invoice_vat' => 'ABC123', 'invoice_address' => '12 Test St. Functown', 'product_id' => '134_2879','product_sku' => 2879, 'product_weight' => 13, 'product_price' => 5699, 'product_quantity' => 21],
            ['customer_id' => 12, 'customer_name' => 'Bob', 'invoice_id' => 152, 'invoice_vat' => 'ABC123', 'invoice_address' => '12 Test St. Functown', 'product_id' => '152_5312','product_sku' => 5312, 'product_weight' => 1.5, 'product_price' => 120, 'product_quantity' => 3],
            ['customer_id' => 12, 'customer_name' => 'Bob', 'invoice_id' => 152, 'invoice_vat' => 'ABC123', 'invoice_address' => '12 Test St. Functown', 'product_id' => '152_2762','product_sku' => 2762, 'product_weight' => 4, 'product_price' => 4320, 'product_quantity' => 4],
            ['customer_id' => 14, 'customer_name' => 'Jane', 'invoice_id' => 163, 'invoice_vat' => 'DEF456', 'invoice_address' => '14 Unit St. Testville', 'product_id' => '163_876','product_sku' => 876, 'product_weight' => 23, 'product_price' => 39050, 'product_quantity' => 12],
            ['customer_id' => 14, 'customer_name' => 'Jane', 'invoice_id' => 163, 'invoice_vat' => 'DEF456', 'invoice_address' => '14 Unit St. Testville', 'product_id' => '163_5312','product_sku' => 5312, 'product_weight' => 1.5, 'product_price' => 120, 'product_quantity' => 16],
            ['customer_id' => 14, 'customer_name' => 'Jane', 'invoice_id' => 172, 'invoice_vat' => 'DEF456', 'invoice_address' => '14 Unit St. Testville', 'product_id' => '172_754','product_sku' => 754, 'product_weight' => 2.35, 'product_price' => 7999, 'product_quantity' => 11],
        ];

        $flatMapperResults = ((new FlatMapper())->map(CustomerDTO::class, $results));

        $product134_5312 = new ProductDTO(5312, 1.5, 120, 5);
        $product134_645 = new ProductDTO(645, 12.7, 350, 2);
        $product134_2879 = new ProductDTO(2879, 13, 5699, 21);
        $product152_5312 = new ProductDTO(5312, 1.5, 120, 3);
        $product152_2762 = new ProductDTO(2762, 4, 4320, 4);
        $product163_876 = new ProductDTO(876, 23, 39050, 12);
        $product163_5312 = new ProductDTO(5312, 1.5, 120, 16);
        $product172_754 = new ProductDTO(754, 2.35, 7999, 11);

        $invoiceDto134 = new InvoiceDTO(134, 'ABC123', '12 Test St. Functown', ['134_5312' => $product134_5312, '134_645' => $product134_645, '134_2879' => $product134_2879]);
        $invoiceDto152 = new InvoiceDTO(152, 'ABC123', '12 Test St. Functown', ['152_5312' => $product152_5312, '152_2762' => $product152_2762]);
        $invoiceDto163 = new InvoiceDTO(163, 'DEF456', '14 Unit St. Testville', ['163_876' => $product163_876, '163_5312' => $product163_5312]);
        $invoiceDto172 = new InvoiceDTO(172, 'DEF456', '14 Unit St. Testville', ['172_754' => $product172_754]);

        $customerDto12 = new CustomerDTO(12, "Bob", [134 => $invoiceDto134, 152 => $invoiceDto152]);
        $customerDto14 = new CustomerDTO(14, "Jane", [163 => $invoiceDto163, 172 => $invoiceDto172]);

        $handmadeResult = [12 => $customerDto12, 14 => $customerDto14];

        $this->assertSame(
            var_export($flatMapperResults, true),
            var_export($handmadeResult, true)
        );

        /** @var CustomerDTO $customerDto */
        $customerDto = $flatMapperResults[12];
        $this->assertEquals(138619, $customerDto->getTotalPurchases());
    }

    public function testMapWithoutAttributeDTO(): void
    {
        $results = [
            ['id' => 1, 'foo' => 'Foo 1', 'bar' => 1],
            ['id' => 2, 'foo' => 'Foo 2', 'bar' => 2],
        ];

        $flatMapperResults = ((new FlatMapper())->map(WithoutAttributeDTO::class, $results));

        $rootDto1 = new WithoutAttributeDTO(1, "Foo 1", 1);
        $rootDto2 = new WithoutAttributeDTO(2, "Foo 2", 2);
        $handmadeResult = [1 => $rootDto1, 2 => $rootDto2];

        $this->assertSame(
            var_export($flatMapperResults, true),
            var_export($handmadeResult, true)
        );
    }

    public function testMapEmptyData(): void
    {
        $flatMapperResults = ((new FlatMapper())->map(ColumnArrayDTO::class, []));

        $this->assertSame(
            var_export($flatMapperResults, true),
            var_export([], true)
        );
    }
}