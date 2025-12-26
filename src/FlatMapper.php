<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle;

use Error;
use Pixelshaped\FlatMapperBundle\Exception\MappingCreationException;
use Pixelshaped\FlatMapperBundle\Exception\MappingException;
use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;
use Pixelshaped\FlatMapperBundle\Mapping\ScalarArray;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Contracts\Cache\CacheInterface;

final class FlatMapper
{
    // Pre-compiled regex patterns for better performance
    private const SNAKE_CASE_PATTERN_1 = '/([A-Z]+)([A-Z][a-z])/';
    private const SNAKE_CASE_PATTERN_2 = '/([a-z\d])([A-Z])/';
    private const SNAKE_CASE_REPLACEMENT = '\1_\2';

    /**
     * @var array<class-string, array<class-string, string>>
     */
    private array $objectIdentifiers = [];
    /**
     * @var array<class-string, array<class-string, array<int|string, null|string>>>
     */
    private array $objectsMapping = [];

    private ?CacheInterface $cacheService = null;
    private bool $validateMapping = true;

    public function setCacheService(CacheInterface $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    public function setValidateMapping(bool $validateMapping): void
    {
        $this->validateMapping = $validateMapping;
    }

    public function createMapping(string $dtoClassName): void
    {
        if(!class_exists($dtoClassName)) {
            throw new MappingCreationException($dtoClassName.' is not a valid class name');
        }
        if(!isset($this->objectsMapping[$dtoClassName])) {

            if($this->cacheService !== null) {
                $cacheKey = strtr($dtoClassName, ['\\' => '_', '-' => '_', ' ' => '_']);
                $mappingInfo = $this->cacheService->get('pixelshaped_flat_mapper_'.$cacheKey, function () use ($dtoClassName): array {
                    return $this->createMappingRecursive($dtoClassName);
                });
            } else {
                $mappingInfo = $this->createMappingRecursive($dtoClassName);
            }

            $this->objectsMapping[$dtoClassName] = $mappingInfo['objectsMapping'];
            $this->objectIdentifiers[$dtoClassName] = $mappingInfo['objectIdentifiers'];
        }
    }

    /**
     * @param class-string $dtoClassName
     * @param array<class-string, string>|null $objectIdentifiers
     * @param array<class-string, array<int|string, null|string>>|null $objectsMapping
     * @return array{'objectIdentifiers': array<class-string, string>, "objectsMapping": array<class-string, array<int|string, null|string>>}
     */
    private function createMappingRecursive(string $dtoClassName, ?array& $objectIdentifiers = null, ?array& $objectsMapping = null): array
    {
        if($objectIdentifiers === null) $objectIdentifiers = [];
        if($objectsMapping === null) $objectsMapping = [];

        $objectIdentifiers = array_merge([$dtoClassName => 'RESERVED'], $objectIdentifiers);

        $reflectionClass = new ReflectionClass($dtoClassName);

        $constructor = $reflectionClass->getConstructor();

        if($constructor === null) {
            throw new MappingCreationException('Class "' . $dtoClassName . '" does not have a constructor.');
        }

        $identifiersCount = 0;
        $transformation   = null;

        foreach ($reflectionClass->getAttributes() as $attribute) {
            switch ($attribute->getName()) {
                case Identifier::class:
                    if (isset($attribute->getArguments()[0]) && $attribute->getArguments()[0] !== null) {
                        $objectIdentifiers[$dtoClassName] = $attribute->getArguments()[0];
                        $identifiersCount++;
                    } else {
                        throw new MappingCreationException('The Identifier attribute cannot be used without a property name when used as a Class attribute');
                    }
                    break;

                case NameTransformation::class:
                    try {
                        /** @var NameTransformation $transformation */
                        $transformation = $attribute->newInstance();
                    } catch (Error $e) {
                        throw new MappingCreationException(sprintf(
                            'Invalid NameTransformation attribute for %s:%s%s',
                            $dtoClassName,
                            PHP_EOL,
                            $e->getMessage()
                        ));
                    }
            }
        }

        foreach ($constructor->getParameters() as $reflectionParameter) {
            $propertyName = $reflectionParameter->getName();
            $columnName   = $transformation
                ? $this->transformPropertyName($propertyName, $transformation)
                : $propertyName;
            $isIdentifier = false;
            foreach ($reflectionParameter->getAttributes() as $attribute) {
                if ($attribute->getName() === ReferenceArray::class || $attribute->getName() === ScalarArray::class) {
                    if($this->validateMapping) {
                        if((new ReflectionProperty($dtoClassName, $propertyName))->isReadOnly()) {
                            throw new MappingCreationException($reflectionClass->getName().': property '.$propertyName.' cannot be readonly as it is non-scalar and '.static::class.' needs to access it after object instantiation.');
                        }
                    }
                    $objectsMapping[$dtoClassName][$propertyName] = (string)$attribute->getArguments()[0];
                    if($attribute->getName() === ReferenceArray::class) {
                        $this->createMappingRecursive($attribute->getArguments()[0], $objectIdentifiers, $objectsMapping);
                    }
                    continue 2;
                } else if ($attribute->getName() === Identifier::class) {
                    $identifiersCount++;
                    $isIdentifier = true;
                    if(isset($attribute->getArguments()[0]) && $attribute->getArguments()[0] !== null) {
                        $columnName = $attribute->getArguments()[0];
                    }
                } else if ($attribute->getName() === Scalar::class) {
                    $columnName = $attribute->getArguments()[0];
                }
            }

            if ($isIdentifier) {
                $objectIdentifiers[$dtoClassName] = $columnName;
            }

            $objectsMapping[$dtoClassName][$columnName] = null;
        }

        if($this->validateMapping) {
            if($identifiersCount !== 1) {
                throw new MappingCreationException($dtoClassName.' does not contain exactly one #[Identifier] attribute.');
            }
            
            $uniqueCheck = [];
            foreach ($objectIdentifiers as $key => $value) {
                if (isset($uniqueCheck[$value])) {
                    throw new MappingCreationException('Several data identifiers are identical: ' . print_r($objectIdentifiers, true));
                }
                $uniqueCheck[$value] = true;
            }
        }

        return [
            'objectIdentifiers' => $objectIdentifiers,
            'objectsMapping' => $objectsMapping
        ];
    }

    private function transformPropertyName(string $propertyName, NameTransformation $transformation): string
    {
        if ($transformation->snakeCaseColumns) {
            $propertyName = strtolower(preg_replace(
                [self::SNAKE_CASE_PATTERN_1, self::SNAKE_CASE_PATTERN_2],
                self::SNAKE_CASE_REPLACEMENT,
                $propertyName
            ) ?? $propertyName);
        }
        return $transformation->columnPrefix . $propertyName;
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClassName
     * @param iterable<array<mixed>> $data
     * @return array<T>
     */
    public function map(string $dtoClassName, iterable $data): array {

        $this->createMapping($dtoClassName);

        $objectsMap = [];
        $referencesMap = [];
        foreach ($data as $row) {
            foreach ($this->objectIdentifiers[$dtoClassName] as $objectClass => $identifier) {
                if (!array_key_exists($identifier, $row)) {
                    throw new MappingException('Identifier not found: ' . $identifier);
                }
                if ($row[$identifier] !== null && !isset($objectsMap[$identifier][$row[$identifier]])) {
                    $constructorValues = [];
                    foreach ($this->objectsMapping[$dtoClassName][$objectClass] as $objectProperty => $foreignObjectClassOrIdentifier) {
                        if($foreignObjectClassOrIdentifier !== null) {
                            if (isset($this->objectsMapping[$dtoClassName][$foreignObjectClassOrIdentifier])) {
                                // Handles ReferenceArray attribute
                                $foreignIdentifier = $this->objectIdentifiers[$dtoClassName][$foreignObjectClassOrIdentifier];
                                if($row[$foreignIdentifier] !== null) {
                                    $referencesMap[$objectClass][$row[$identifier]][$objectProperty][$row[$foreignIdentifier]] = $objectsMap[$foreignObjectClassOrIdentifier][$row[$foreignIdentifier]];
                                }
                            } else {
                                // Handles ScalarArray attribute
                                if($row[$foreignObjectClassOrIdentifier] !== null) {
                                    $referencesMap[$objectClass][$row[$identifier]][$objectProperty][] = $row[$foreignObjectClassOrIdentifier];
                                }
                            }
                            $constructorValues[] = [];
                        } else {
                            if(!array_key_exists($objectProperty, $row)) {
                                throw new MappingException('Data does not contain required property: ' . $objectProperty);
                            }
                            $constructorValues[] = $row[$objectProperty];
                        }
                    }
                    try {
                        $objectsMap[$objectClass][$row[$identifier]] = new $objectClass(...$constructorValues);
                    } catch (\TypeError $e) {
                        throw new MappingException('Cannot construct object: '.$e->getMessage());
                    }
                }
            }
        }

        $this->linkObjects($referencesMap, $objectsMap);

        /** @var array<T>  $rootObjects */
        $rootObjects = array_key_exists($dtoClassName, $objectsMap) ? $objectsMap[$dtoClassName] : [];
        return $rootObjects;
    }

    /**
     * @template T of object
     * @param array<class-string<T>, array<array<mixed>>> $referencesMap
     * @param array<class-string<T>, array<int|string, T>> $objectsMap
     */
    private function linkObjects(array $referencesMap, array $objectsMap): void
    {
        foreach ($referencesMap as $objectClass => $references) {
            foreach ($references as $identifier => $foreignObjects) {
                foreach ($foreignObjects as $mappedProperty => $foreignObjectIdentifiers) {
                    if (isset($objectsMap[$objectClass][$identifier])) {
                        (new ReflectionProperty($objectClass, $mappedProperty))
                            ->setValue($objectsMap[$objectClass][$identifier], $foreignObjectIdentifiers);
                    }
                }
            }
        }
    }
}
