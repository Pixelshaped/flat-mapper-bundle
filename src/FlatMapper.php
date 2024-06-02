<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle;

use Pixelshaped\FlatMapperBundle\Attributes\ColumnArray;
use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;
use Pixelshaped\FlatMapperBundle\Attributes\ReferencesArray;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;

class FlatMapper
{

    private array $objectIdentifiers = [];
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
    /**
     * @throws ReflectionException
     */
    public function createMapping(string $dtoClassName): void
    {
        dd($this->cacheService, $this->validateMapping);
        // We do this array_merge so that the identifier is created at the proper position in the $this->objectIdentifiers array
        $this->objectIdentifiers = array_merge([$dtoClassName => null], $this->objectIdentifiers);

        $reflectionClass = new ReflectionClass($dtoClassName);

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();
            $isIdentifier = false;
            foreach ($reflectionProperty->getAttributes() as $attribute) {
                if ($attribute->getName() === ReferencesArray::class) {
                    $this->objectsMapping[$dtoClassName][$propertyName] = $attribute->getArguments()[0];
                    $this->createMapping($attribute->getArguments()[0]);
                    continue 2;
                }

                if ($attribute->getName() === ColumnArray::class) {
                    $this->objectsMapping[$dtoClassName][$propertyName] = $attribute->getArguments()[0];
                    continue 2;
                }

                if ($attribute->getName() === Identifier::class) {
                    $isIdentifier = true;
                }

                if ($attribute->getName() === InboundPropertyName::class) {
                    $propertyName = $attribute->getArguments()[0];
                }
            }

            if ($isIdentifier) {
                $this->objectIdentifiers[$dtoClassName] = $propertyName;
            }

            $this->objectsMapping[$dtoClassName][$propertyName] = null;
        }

        if (count($this->objectIdentifiers) !== count(array_unique($this->objectIdentifiers))) {
            throw new RuntimeException('Several identifiers are identical: ' . print_r($this->objectIdentifiers, true));
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClassName
     * @param array<array<mixed>> $data
     * @return T
     * @throws ReflectionException
     */
    public function map(string $dtoClassName, array $data): mixed {

        $this->createMapping($dtoClassName);

        $objectsMap = [];
        $referencesMap = [];
        foreach ($data as $row) {
            foreach ($this->objectIdentifiers as $objectClass => $identifier) {
                if (!isset($row[$identifier])) {
                    throw new RuntimeException('Identifier not found: ' . $identifier);
                }
                if (!isset($objectsMap[$identifier][$row[$identifier]])) {
                    $constructorValues = [];
                    foreach ($this->objectsMapping[$objectClass] as $objectProperty => $foreignObjectClassOrIdentifier) {
                        if($foreignObjectClassOrIdentifier !== null) {
                            if (isset($this->objectsMapping[$foreignObjectClassOrIdentifier])) {
                                // it's a reference, let's initialize an array
                                $foreignIdentifier = $this->objectIdentifiers[$foreignObjectClassOrIdentifier];
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
                    $objectsMap[$objectClass][$row[$identifier]] = new $objectClass(...$constructorValues);
                }
            }
        }

        $this->linkObjects($referencesMap, $objectsMap);

        return $objectsMap[$dtoClassName];
    }

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
