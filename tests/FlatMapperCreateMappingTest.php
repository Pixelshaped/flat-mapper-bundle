<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Pixelshaped\FlatMapperBundle\Exception\MappingCreationException;
use Pixelshaped\FlatMapperBundle\FlatMapper;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTO as InvalidRootDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithEmptyClassIdentifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithNoIdentifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithoutConstructor;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithTooManyIdentifiers;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ColumnArray\ColumnArrayDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ReferencesArray\AuthorDTO;
use Symfony\Contracts\Cache\CacheInterface;

#[CoversMethod(FlatMapper::class, 'createMapping')]
#[CoversMethod(FlatMapper::class, 'createMappingRecursive')]
#[CoversMethod(FlatMapper::class, 'setCacheService')]
#[CoversClass(MappingCreationException::class)]
class FlatMapperCreateMappingTest extends TestCase
{
    public function testCreateMappingWithValidDTOsDoesNotAssert(): void
    {
        $this->expectNotToPerformAssertions();
        (new FlatMapper())->createMapping(ColumnArrayDTO::class);
        (new FlatMapper())->createMapping(AuthorDTO::class);
    }

    public function testCreateMappingWithCacheServiceDoesNotAssert(): void
    {
        $flatMapper = new FlatMapper();
        $reflectionMethod = (new \ReflectionClass(FlatMapper::class))->getMethod('createMappingRecursive');
        $reflectionMethod->setAccessible(true);

        $cacheInterface = $this->createMock(CacheInterface::class);
        $cacheInterface->expects($this->once())->method('get')->willReturn(
            $reflectionMethod->invoke($flatMapper, AuthorDTO::class)
        );
        
        $flatMapper->setCacheService($cacheInterface);
        $flatMapper->createMapping(AuthorDTO::class);
    }

    public function testCreateMappingWrongClassNameAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/An error occurred during mapping creation: ThisIsNotAValidClassString is not a valid class name/");
        (new FlatMapper())->createMapping('ThisIsNotAValidClassString');
    }

    public function testCreateMappingWithSeveralIdenticalIdentifiersAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/Several data identifiers are identical/");
        (new FlatMapper())->createMapping(InvalidRootDTO::class);
    }

    public function testCreateMappingWithTooManyIdentifiersAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/does not contain exactly one #\[Identifier\] attribute/");
        (new FlatMapper())->createMapping(RootDTOWithTooManyIdentifiers::class);
    }

    public function testCreateMappingWithNoIdentifierAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/does not contain exactly one #\[Identifier\] attribute/");
        (new FlatMapper())->createMapping(RootDTOWithNoIdentifier::class);
    }

    public function testCreateMappingWithNoConstructorAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/does not have a constructor/");
        (new FlatMapper())->createMapping(RootDTOWithoutConstructor::class);
    }

    public function testCreateMappingWithEmptyClassIdentifierAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/The Identifier attribute cannot be used without a property name when used as a Class attribute/");
        (new FlatMapper())->createMapping(RootDTOWithEmptyClassIdentifier::class);
    }
}
