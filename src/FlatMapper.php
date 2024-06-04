<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle;

use App\Entity\Main\GlobalConfig;
use App\Utils\Constants\CacheKeys;
use Pixelshaped\FlatMapperBundle\Attributes\ColumnArray;
use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;
use Pixelshaped\FlatMapperBundle\Attributes\ReferencesArray;
use ReflectionClass;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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
        if(!isset($this->objectsMapping[$dtoClassName])) {

            if($this->cacheService !== null) {
                $cacheKey = preg_replace("/([^a-zA-Z0-9]+)/","_", $dtoClassName);;
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
     * @return array{'objectIdentifiers': array<class-string, string>, "objectsMapping": array<class-string, array<int|string, null|string>>}
     */
    private function createMappingRecursive(string $dtoClassName, array& $objectIdentifiers = null, array& $objectsMapping = null): array
    {
        if($objectIdentifiers === null && $objectsMapping === null) {
            $objectIdentifiers = [];
            $objectsMapping = [];
        }

        $objectIdentifiers = array_merge([$dtoClassName => 'RESERVED'], $objectIdentifiers);

        $reflectionClass = new ReflectionClass($dtoClassName);

        $constructor = $reflectionClass->getConstructor();

        if($constructor === null) {
            throw new RuntimeException('Class "' . $dtoClassName . '" does not have a constructor.');
        }

        $identifiersCount = 0;
        foreach ($constructor->getParameters() as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();
            $isIdentifier = false;
            foreach ($reflectionProperty->getAttributes() as $attribute) {
                if ($attribute->getName() === ReferencesArray::class) {
                    $objectsMapping[$dtoClassName][$propertyName] = (string)$attribute->getArguments()[0];
                    $this->createMappingRecursive($attribute->getArguments()[0], $objectIdentifiers, $objectsMapping);
                    continue 2;
                } else if ($attribute->getName() === ColumnArray::class) {
                    $objectsMapping[$dtoClassName][$propertyName] = (string)$attribute->getArguments()[0];
                    continue 2;
                } else if ($attribute->getName() === Identifier::class) {
                    $identifiersCount++;
                    $isIdentifier = true;
                } else if ($attribute->getName() === InboundPropertyName::class) {
                    $propertyName = $attribute->getArguments()[0];
                }
            }

            if ($isIdentifier) {
                $objectIdentifiers[$dtoClassName] = $propertyName;
            }

            $objectsMapping[$dtoClassName][$propertyName] = null;
        }

        if($this->validateMapping) {
            if($identifiersCount !== 1) {
                throw new RuntimeException($dtoClassName.' does not contain exactly one #[Identifier] attribute.');
            }

            if (count($objectIdentifiers) !== count(array_unique($objectIdentifiers))) {
                throw new RuntimeException('Several data identifiers are identical: ' . print_r($objectIdentifiers, true));
            }
        }

        return [
            'objectIdentifiers' => $objectIdentifiers,
            'objectsMapping' => $objectsMapping
        ];
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClassName
     * @param array<array<mixed>> $data
     * @return array<T>
     */
    public function map(string $dtoClassName, array $data): array {

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
