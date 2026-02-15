<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle;

use Pixelshaped\FlatMapperBundle\Exception\MappingCreationException;
use Pixelshaped\FlatMapperBundle\Exception\MappingException;
use ReflectionProperty;
use Symfony\Contracts\Cache\CacheInterface;

final class FlatMapper
{
    /**
     * @var array<class-string, array{
     *     objectIdentifiers: array<class-string, string>,
     *     objectsMapping: array<class-string, array<int|string, null|string>>
     * }>
     */
    private array $mappings = [];

    private ?CacheInterface $cacheService = null;
    private MappingResolver $mappingResolver;

    public function __construct()
    {
        $this->mappingResolver = new MappingResolver();
    }

    public function setCacheService(CacheInterface $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    public function setValidateMapping(bool $validateMapping): void
    {
        $this->mappingResolver->setValidateMapping($validateMapping);
    }

    /**
     * @param array<class-string, array{
     *     class?: array<string, mixed>,
     *     properties?: array<string, array<string, mixed>>
     * }> $yamlMappings
     */
    public function setYamlMappings(array $yamlMappings): void
    {
        $this->mappingResolver->setYamlMappings($yamlMappings);
        $this->mappings = [];
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClassName
     * @param iterable<array<mixed>> $data
     * @return array<T>
     */
    public function map(string $dtoClassName, iterable $data): array {

        $this->createMapping($dtoClassName);
        $mapping = $this->mappings[$dtoClassName];
        $objectIdentifiers = $mapping['objectIdentifiers'];
        $objectsMapping = $mapping['objectsMapping'];

        $objectsMap = [];
        $referencesMap = [];
        foreach ($data as $row) {
            foreach ($objectIdentifiers as $objectClass => $identifier) {
                if (!array_key_exists($identifier, $row)) {
                    throw new MappingException('Identifier not found: ' . $identifier);
                }
                if ($row[$identifier] !== null && !isset($objectsMap[$identifier][$row[$identifier]])) {
                    $constructorValues = [];
                    foreach ($objectsMapping[$objectClass] as $objectProperty => $foreignObjectClassOrIdentifier) {
                        if($foreignObjectClassOrIdentifier !== null) {
                            if (isset($objectsMapping[$foreignObjectClassOrIdentifier])) {
                                // Handles ReferenceArray attribute
                                $foreignIdentifier = $objectIdentifiers[$foreignObjectClassOrIdentifier];
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

    public function createMapping(string $dtoClassName): void
    {
        if(!class_exists($dtoClassName)) {
            throw new MappingCreationException($dtoClassName.' is not a valid class name');
        }
        if(!isset($this->mappings[$dtoClassName])) {
            if($this->cacheService !== null) {
                $mappingInfo = $this->cacheService->get($this->createCacheKey($dtoClassName), function () use ($dtoClassName): array {
                    return $this->mappingResolver->resolve($dtoClassName);
                });
            } else {
                $mappingInfo = $this->mappingResolver->resolve($dtoClassName);
            }

            $this->mappings[$dtoClassName] = $mappingInfo;
        }
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

    /**
     * Keep cache keys deterministic while invalidating when YAML mapping changes.
     */
    private function createCacheKey(string $dtoClassName): string
    {
        $cacheKey = strtr($dtoClassName, ['\\' => '_', '-' => '_', ' ' => '_']);
        $mappingHash = $this->mappingResolver->getCacheSignature($dtoClassName);

        return 'pixelshaped_flat_mapper_'.$cacheKey.'_'.$mappingHash;
    }
}
