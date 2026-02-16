<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Pixelshaped\FlatMapperBundle\Exception\MappingCreationException;
use Pixelshaped\FlatMapperBundle\FlatMapper;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\Circular\CycleRootDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\NameTransformation\InvalidNameTransformationDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTO as InvalidRootDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithEmptyClassIdentifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithEmptyStringClassIdentifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithEmptyStringPropertyIdentifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootAbstractDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithInvalidReferenceArrayAttribute;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithInvalidReferenceArrayClass;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithNonStringScalarArgument;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithInvalidScalarArrayAttribute;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithInvalidScalarAttribute;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithNoIdentifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithoutConstructor;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithReadonlyClassModifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\RootDTOWithTooManyIdentifiers;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ReferenceArray\AuthorDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ScalarArray\ScalarArrayDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ScalarDTOWithReadonlyClassModifier;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Yaml\AuthorDTO as YamlAuthorDTO;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Yaml\BookDTO as YamlBookDTO;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversMethod(FlatMapper::class, 'createMapping')]
#[CoversMethod(FlatMapper::class, 'createMappingRecursive')]
#[CoversMethod(FlatMapper::class, 'getAttributeArgument')]
#[CoversMethod(FlatMapper::class, 'setCacheService')]
#[CoversMethod(FlatMapper::class, 'setYamlMappings')]
#[CoversClass(FlatMapper::class)]
#[CoversClass(MappingCreationException::class)]
class FlatMapperCreateMappingTest extends TestCase
{
    public function testCreateMappingWithValidDTOsDoesNotAssert(): void
    {
        $this->expectNotToPerformAssertions();
        (new FlatMapper())->createMapping(ScalarArrayDTO::class);
        (new FlatMapper())->createMapping(AuthorDTO::class);
    }

    public function testCreateMappingWithCacheServiceDoesNotAssert(): void
    {
        $flatMapper = new FlatMapper();

        // The intention is not to test the createMappingRecursive private method
        // but to dynamically give the CacheInterface mock a proper return value.
        $reflectionMethod = (new \ReflectionClass(FlatMapper::class))->getMethod('createMappingRecursive');
        $reflectionMethod->setAccessible(true);
        $cacheInterface = $this->createMock(CacheInterface::class);
        $cacheInterface->expects($this->once())->method('get')->willReturn(
            $reflectionMethod->invoke($flatMapper, AuthorDTO::class)
        );

        $flatMapper->setCacheService($cacheInterface);
        $flatMapper->createMapping(AuthorDTO::class);
    }

    public function testCreateMappingWithCacheServiceMissExecutesCallback(): void
    {
        $flatMapper = new FlatMapper();

        $cacheInterface = $this->createMock(CacheInterface::class);
        $cacheInterface->expects($this->once())->method('get')->willReturnCallback(
            function (string $key, callable $callback) {
                return $callback();
            }
        );

        $flatMapper->setCacheService($cacheInterface);
        $flatMapper->createMapping(AuthorDTO::class);
    }

    public function testCreateMappingWithCacheServiceInvalidatesRootCacheWhenNestedYamlMappingChanges(): void
    {
        $cachedMappings = [];
        $cacheItem = $this->createStub(ItemInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            function (string $key, callable $callback) use (&$cachedMappings, $cacheItem): mixed {
                if (!array_key_exists($key, $cachedMappings)) {
                    $save = true;
                    $cachedMappings[$key] = $callback($cacheItem, $save);
                }

                return $cachedMappings[$key];
            }
        );

        $flatMapper = new FlatMapper();
        $flatMapper->setCacheService($cache);

        $authorMapping = [
            'class' => [
                'NameTransformation' => ['columnPrefix' => 'author_'],
            ],
            'properties' => [
                'id' => ['Identifier' => null],
                'books' => ['ReferenceArray' => YamlBookDTO::class],
            ],
        ];

        $flatMapper->setYamlMappings([
            YamlAuthorDTO::class => $authorMapping,
            YamlBookDTO::class => [
                'class' => [
                    'NameTransformation' => ['columnPrefix' => 'book_', 'snakeCaseColumns' => true],
                ],
                'properties' => [
                    'id' => ['Identifier' => null],
                ],
            ],
        ]);
        $flatMapper->map(YamlAuthorDTO::class, [[
            'author_id' => 1,
            'author_name' => 'Alice',
            'book_id' => 10,
            'book_name' => 'Original title',
            'book_publisher_name' => 'Original publisher',
        ]]);

        $flatMapper->setYamlMappings([
            YamlAuthorDTO::class => $authorMapping,
            YamlBookDTO::class => [
                'properties' => [
                    'id' => ['Identifier' => 'book_id'],
                    'name' => ['Scalar' => 'book_title'],
                    'publisherName' => ['Scalar' => 'book_publisher'],
                ],
            ],
        ]);
        $mappedResults = $flatMapper->map(YamlAuthorDTO::class, [[
            'author_id' => 1,
            'author_name' => 'Alice',
            'book_id' => 10,
            'book_title' => 'Updated title',
            'book_publisher' => 'Updated publisher',
        ]]);

        $this->assertSame('Updated title', $mappedResults[1]->books[10]->name);
        $this->assertSame('Updated publisher', $mappedResults[1]->books[10]->publisherName);
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

    public function testCreateMappingWithReadonlyModifierOnNonScalarDtoAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/cannot be readonly as it is non-scalar and/");
        (new FlatMapper())->createMapping(RootDTOWithReadonlyClassModifier::class);
    }

    public function testCreateMappingWithReadonlyModifierOnScalarDtoSucceeds(): void
    {
        $this->expectNotToPerformAssertions();
        (new FlatMapper())->createMapping(ScalarDTOWithReadonlyClassModifier::class);
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

    public function testCreateMappingWithAbstractClassAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/is not instantiable/');
        (new FlatMapper())->createMapping(RootAbstractDTO::class);
    }

    public function testCreateMappingWithEmptyClassIdentifierAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/The Identifier attribute cannot be used without a property name when used as a Class attribute/");
        (new FlatMapper())->createMapping(RootDTOWithEmptyClassIdentifier::class);
    }

    public function testCreateMappingWithEmptyStringClassIdentifierAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/The Identifier attribute cannot be used without a property name when used as a Class attribute/");
        (new FlatMapper())->createMapping(RootDTOWithEmptyStringClassIdentifier::class);
    }

    public function testCreateMappingWithEmptyStringPropertyIdentifierAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Invalid Identifier attribute/');
        (new FlatMapper())->createMapping(RootDTOWithEmptyStringPropertyIdentifier::class);
    }

    public function testCreateMappingWithInvalidNameTransformationAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches("/Invalid NameTransformation attribute/");
        (new FlatMapper())->createMapping(InvalidNameTransformationDTO::class);
    }

    public function testCreateMappingWithCircularReferenceArrayAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Circular ReferenceArray mapping detected/');
        (new FlatMapper())->createMapping(CycleRootDTO::class);
    }

    public function testCreateMappingWithInvalidReferenceArrayClassAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/This\\\\Class\\\\Does\\\\Not\\\\Exist is not a valid class name/');
        (new FlatMapper())->createMapping(RootDTOWithInvalidReferenceArrayClass::class);
    }

    public function testCreateMappingWithInvalidReferenceArrayAttributeAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Invalid ReferenceArray attribute/');
        (new FlatMapper())->createMapping(RootDTOWithInvalidReferenceArrayAttribute::class);
    }

    public function testCreateMappingWithInvalidScalarArrayAttributeAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Invalid ScalarArray attribute/');
        (new FlatMapper())->createMapping(RootDTOWithInvalidScalarArrayAttribute::class);
    }

    public function testCreateMappingWithInvalidScalarAttributeAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Invalid Scalar attribute/');
        (new FlatMapper())->createMapping(RootDTOWithInvalidScalarAttribute::class);
    }

    public function testCreateMappingWithNonStringScalarArgumentAsserts(): void
    {
        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Expected string, got int/');
        (new FlatMapper())->createMapping(RootDTOWithNonStringScalarArgument::class);
    }

    public function testCreateMappingWithYamlMappingsDoesNotAssert(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlAuthorDTO::class => [
                'class' => [
                    'NameTransformation' => ['columnPrefix' => 'author_'],
                ],
                'properties' => [
                    'id' => ['Identifier' => null],
                    'books' => ['ReferenceArray' => YamlBookDTO::class],
                ],
            ],
            YamlBookDTO::class => [
                'class' => [
                    'NameTransformation' => ['columnPrefix' => 'book_', 'snakeCaseColumns' => true],
                ],
                'properties' => [
                    'id' => ['Identifier' => null],
                ],
            ],
        ]);

        $this->expectNotToPerformAssertions();
        $flatMapper->createMapping(YamlAuthorDTO::class);
    }

    public function testCreateMappingWithInvalidYamlMappingShapeAsserts(): void
    {
        $flatMapper = new FlatMapper();
        // @phpstan-ignore argument.type
        $flatMapper->setYamlMappings([
            YamlAuthorDTO::class => [
                'properties' => [
                    'id' => 'invalid',
                ],
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Invalid YAML mapping/');
        $flatMapper->createMapping(YamlAuthorDTO::class);
    }

    public function testCreateMappingWithInvalidYamlAttributeClassAsserts(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlAuthorDTO::class => [
                'properties' => [
                    'id' => ['UnknownAttribute' => 'author_id'],
                ],
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Attribute class/');
        $flatMapper->createMapping(YamlAuthorDTO::class);
    }

    public function testCreateMappingWithUnsupportedYamlAttributeClassAsserts(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => [
                'properties' => [
                    'id' => [FlatMapper::class => []],
                ],
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Unsupported mapping attribute class/');
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testCreateMappingWithYamlClassAttributesSetToNullDoesNotAssert(): void
    {
        $flatMapper = new FlatMapper();
        // @phpstan-ignore argument.type
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => [
                'class' => null,
                'properties' => [
                    'id' => ['Identifier' => 'book_id'],
                ],
            ],
        ]);

        $this->expectNotToPerformAssertions();
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testCreateMappingWithYamlNamedArgumentsDoesNotAssert(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => [
                'properties' => [
                    'id' => ['Identifier' => ['mappedPropertyName' => 'book_id']],
                ],
            ],
        ]);

        $this->expectNotToPerformAssertions();
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testCreateMappingWithYamlFullyQualifiedAttributeNamesDoesNotAssert(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => [
                'properties' => [
                    'id' => [\Pixelshaped\FlatMapperBundle\Mapping\Identifier::class => 'book_id'],
                ],
            ],
        ]);

        $this->expectNotToPerformAssertions();
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testCreateMappingWithYamlAndReflectionAttributesOverlapDoesNotAssert(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            AuthorDTO::class => [
                'properties' => [
                    'id' => [
                        'Identifier' => 'yaml_author_id',
                        'Scalar' => 'yaml_author_id',
                    ],
                ],
            ],
        ]);

        $this->expectNotToPerformAssertions();
        $flatMapper->createMapping(AuthorDTO::class);
    }

    public function testCreateMappingWithInvalidYamlClassDefinitionAsserts(): void
    {
        $flatMapper = new FlatMapper();
        // @phpstan-ignore argument.type
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => 'invalid',
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Expected an array/');
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testCreateMappingWithInvalidYamlPropertiesDefinitionAsserts(): void
    {
        $flatMapper = new FlatMapper();
        // @phpstan-ignore argument.type
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => [
                'properties' => 'invalid',
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/The "properties" section must be an array/');
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testCreateMappingWithYamlNonStringAttributeNameAsserts(): void
    {
        $flatMapper = new FlatMapper();
        // @phpstan-ignore argument.type
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => [
                'properties' => [
                    'id' => [0 => 'book_id'],
                ],
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Attribute names must be strings/');
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testCreateMappingWithYamlInvalidAttributeArgumentTypeAsserts(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => [
                'properties' => [
                    'id' => ['Identifier' => new \stdClass()],
                ],
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Expected null, scalar, or array arguments/');
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testCreateMappingWithYamlReferenceArrayWithoutMappedValueAsserts(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlAuthorDTO::class => [
                'properties' => [
                    'id' => ['Identifier' => 'author_id'],
                    'books' => ['ReferenceArray' => null],
                ],
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/requires a mapped value/');
        $flatMapper->createMapping(YamlAuthorDTO::class);
    }

    public function testCreateMappingWithYamlInvalidReferenceClassAsserts(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlAuthorDTO::class => [
                'properties' => [
                    'id' => ['Identifier' => 'author_id'],
                    'books' => ['ReferenceArray' => 'This\\Class\\Does\\NotExist'],
                ],
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Invalid reference class/');
        $flatMapper->createMapping(YamlAuthorDTO::class);
    }

    public function testCreateMappingWithYamlScalarNonStringArgumentAsserts(): void
    {
        $flatMapper = new FlatMapper();
        $flatMapper->setYamlMappings([
            YamlBookDTO::class => [
                'properties' => [
                    'id' => ['Identifier' => 'book_id'],
                    'name' => ['Scalar' => 123],
                ],
            ],
        ]);

        $this->expectException(MappingCreationException::class);
        $this->expectExceptionMessageMatches('/Expected string, got int/');
        $flatMapper->createMapping(YamlBookDTO::class);
    }

    public function testNormalizeYamlAttributeMapWithNullReturnsEmptyArray(): void
    {
        $flatMapper = new FlatMapper();
        $reflectionMethod = (new \ReflectionClass(FlatMapper::class))->getMethod('normalizeYamlAttributeMap');
        $reflectionMethod->setAccessible(true);

        /** @var array<class-string, array<int|string, mixed>> $result */
        $result = $reflectionMethod->invoke($flatMapper, null, 'class "Foo\\Bar\\Baz"');
        $this->assertSame([], $result);
    }
}
