<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle;

use Pixelshaped\FlatMapperBundle\Attributes\ColumnArray;
use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;
use Pixelshaped\FlatMapperBundle\Attributes\ReferencesArray;
use ReflectionClass;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;

class FlatMapper
{

    /**
     * @var array<class-string, array<class-string, string>>
     */
    private array $objectIdentifiers = [];
    /**
     * @var array<class-string, array<class-string, array<int|string, null|string>>>
     */
    private array $objectsMapping = [];

    // TODO for now those are unused
    private ?CacheInterface $cacheService = null; // @phpstan-ignore-line
    private bool $validateMapping = true;

    public function setCacheService(CacheInterface $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    public function setValidateMapping(bool $validateMapping): void
    {
        $this->validateMapping = $validateMapping;
    }

    /**
     * @param class-string $dtoClassName
     */
    public function createMapping(string $dtoClassName): void
    {
        $this->createMappingRecursive($dtoClassName, $dtoClassName);
    }

    /**
     * @param class-string $dtoClassName
     * @param class-string $rootDtoClassName
     */
    private function createMappingRecursive(string $dtoClassName, string $rootDtoClassName): void
    {
        if(!isset($this->objectIdentifiers[$rootDtoClassName])) $this->objectIdentifiers[$rootDtoClassName] = [];

        $this->objectIdentifiers[$rootDtoClassName] = array_merge([$dtoClassName => 'reserved'], $this->objectIdentifiers[$rootDtoClassName]);

        $reflectionClass = new ReflectionClass($dtoClassName);

        $identifiersCount = 0;
        foreach ($reflectionClass->getConstructor()->getParameters() as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();
            $isIdentifier = false;
            foreach ($reflectionProperty->getAttributes() as $attribute) {
                if ($attribute->getName() === ReferencesArray::class) {
                    $this->objectsMapping[$rootDtoClassName][$dtoClassName][$propertyName] = (string)$attribute->getArguments()[0];
                    $this->createMappingRecursive($attribute->getArguments()[0], $rootDtoClassName);
                    continue 2;
                } else if ($attribute->getName() === ColumnArray::class) {
                    $this->objectsMapping[$rootDtoClassName][$dtoClassName][$propertyName] = (string)$attribute->getArguments()[0];
                    continue 2;
                } else if ($attribute->getName() === Identifier::class) {
                    $identifiersCount++;
                    $isIdentifier = true;
                } else if ($attribute->getName() === InboundPropertyName::class) {
                    $propertyName = $attribute->getArguments()[0];
                }
            }

            if ($isIdentifier) {
                $this->objectIdentifiers[$rootDtoClassName][$dtoClassName] = $propertyName;
            }

            $this->objectsMapping[$rootDtoClassName][$dtoClassName][$propertyName] = null;
        }

        if($this->validateMapping) {
            if($identifiersCount !== 1) {
                throw new RuntimeException($dtoClassName.' contains more than one #[Identifier] attribute.');
            }

            if (count($this->objectIdentifiers[$rootDtoClassName]) !== count(array_unique($this->objectIdentifiers[$rootDtoClassName]))) {
                throw new RuntimeException('Several data identifiers are identical: ' . print_r($this->objectIdentifiers[$rootDtoClassName], true));
            }
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClassName
     * @param array<array<mixed>> $data
     * @return array<T>
     */
    public function map(string $dtoClassName, array $data): mixed {

        $this->createMapping($dtoClassName);

        $objectsMap = [];
        $referencesMap = [];
        foreach ($data as $row) {
            foreach ($this->objectIdentifiers[$dtoClassName] as $objectClass => $identifier) {
                if (!isset($row[$identifier])) {
                    throw new RuntimeException('Identifier not found: ' . $identifier);
                }
                if (!isset($objectsMap[$identifier][$row[$identifier]])) {
                    $constructorValues = [];
                    foreach ($this->objectsMapping[$dtoClassName][$objectClass] as $objectProperty => $foreignObjectClassOrIdentifier) {
                        if($foreignObjectClassOrIdentifier !== null) {
                            if (isset($this->objectsMapping[$dtoClassName][$foreignObjectClassOrIdentifier])) {
                                $foreignIdentifier = $this->objectIdentifiers[$dtoClassName][$foreignObjectClassOrIdentifier];
                                if (!isset($row[$foreignIdentifier])) {
                                    throw new RuntimeException('Foreign identifier not found: ' . $foreignIdentifier);
                                }
                                $referencesMap[$objectClass][$row[$identifier]][$objectProperty][$row[$foreignIdentifier]] = $objectsMap[$foreignObjectClassOrIdentifier][$row[$foreignIdentifier]];
                                $constructorValues[] = [];
                            } else if (isset($row[$foreignObjectClassOrIdentifier])) {
                                $referencesMap[$objectClass][$row[$identifier]][$objectProperty][] = $row[$foreignObjectClassOrIdentifier];
                                $constructorValues[] = [];
                            } else {
                                throw new RuntimeException($foreignObjectClassOrIdentifier.' is neither a foreign identifier nor a foreign object class.');
                            }
                        } else {
                            $constructorValues[] = $row[$objectProperty];
                        }
                    }
                    $dtoInstance = new $objectClass(...$constructorValues);
                    $objectsMap[$objectClass][$row[$identifier]] = $dtoInstance;
                }
            }
        }

        $this->linkObjects($referencesMap, $objectsMap);

        /** @var array<T>  $rootObjects */
        $rootObjects = $objectsMap[$dtoClassName];
        return $rootObjects;
    }

    /**
     * @template T of object
     * @param array<class-string<T>, array<array<mixed>>> $referencesMap
     * @param array<class-string, array<int|string, T>> $objectsMap
     */
    private function linkObjects(array $referencesMap, array $objectsMap): void
    {
        foreach ($referencesMap as $objectClass => $references) {
            foreach ($references as $identifier => $foreignObjects) {
                foreach ($foreignObjects as $mappedProperty => $foreignObjectIdentifiers) {
                    if (isset($objectsMap[$objectClass][$identifier])) {
                        $reflectionClass = new ReflectionClass($objectClass);
                        $arrayProperty = $reflectionClass->getProperty($mappedProperty);
                        $arrayProperty->setValue($objectsMap[$objectClass][$identifier], $foreignObjectIdentifiers);
                    }
                }
            }
        }
    }
}
