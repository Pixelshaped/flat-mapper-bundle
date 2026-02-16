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
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Contracts\Cache\CacheInterface;
use TypeError;
use function in_array;

final class FlatMapper
{
    // Pre-compiled regex patterns for better performance
    private const SNAKE_CASE_PATTERN_1 = '/([A-Z]+)([A-Z][a-z])/';
    private const SNAKE_CASE_PATTERN_2 = '/([a-z\d])([A-Z])/';
    private const SNAKE_CASE_REPLACEMENT = '\1_\2';
    private const MAPPING_NAMESPACE_PREFIX = 'Pixelshaped\\FlatMapperBundle\\Mapping\\';

    /**
     * @var array<class-string, array<class-string, string>>
     */
    private array $objectIdentifiers = [];
    /**
     * @var array<class-string, array<class-string, array<int|string, null|string>>>
     */
    private array $objectsMapping = [];
    /**
     * @var array<class-string, array{
     *     class?: array<string, mixed>,
     *     properties?: array<string, array<string, mixed>>
     * }>
     */
    private array $yamlMappings = [];

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
     * @param array<class-string, array{
     *     class?: array<string, mixed>,
     *     properties?: array<string, array<string, mixed>>
     * }> $yamlMappings
     */
    public function setYamlMappings(array $yamlMappings): void
    {
        $this->yamlMappings = $yamlMappings;

        // Mapping source changed, so in-memory compiled mappings need to be rebuilt.
        $this->objectIdentifiers = [];
        $this->objectsMapping = [];
    }

    public function createMapping(string $dtoClassName): void
    {
        if(!class_exists($dtoClassName)) {
            throw new MappingCreationException($dtoClassName.' is not a valid class name');
        }
        if(!isset($this->objectsMapping[$dtoClassName])) {

            if($this->cacheService !== null) {
                $mappingInfo = $this->cacheService->get($this->createCacheKey($dtoClassName), function () use ($dtoClassName): array {
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
     * @param list<class-string> $mappingPath
     * @return array{'objectIdentifiers': array<class-string, string>, "objectsMapping": array<class-string, array<int|string, null|string>>}
     */
    private function createMappingRecursive(string $dtoClassName, ?array& $objectIdentifiers = null, ?array& $objectsMapping = null, array $mappingPath = []): array
    {
        if (in_array($dtoClassName, $mappingPath, true)) {
            throw new MappingCreationException('Circular ReferenceArray mapping detected: ' . implode(' -> ', [...$mappingPath, $dtoClassName]));
        }

        $mappingPath[] = $dtoClassName;

        if($objectIdentifiers === null) $objectIdentifiers = [];
        if($objectsMapping === null) $objectsMapping = [];

        $objectIdentifiers = array_merge([$dtoClassName => 'RESERVED'], $objectIdentifiers);

        $reflectionClass = new ReflectionClass($dtoClassName);

        if (!$reflectionClass->isInstantiable()) {
            throw new MappingCreationException('Class "' . $dtoClassName . '" is not instantiable.');
        }

        $constructor = $reflectionClass->getConstructor();

        if($constructor === null) {
            throw new MappingCreationException('Class "' . $dtoClassName . '" does not have a constructor.');
        }

        $identifiersCount = 0;
        $transformation   = null;

        foreach ($this->getClassMappingAttributes($reflectionClass) as $attribute) {
            switch ($attribute['name']) {
                case Identifier::class:
                    $identifierPropertyName = $this->getStringMappingArgument(
                        $attribute,
                        'mappedPropertyName',
                        sprintf('class "%s"', $dtoClassName)
                    );
                    if ($identifierPropertyName !== null && $identifierPropertyName !== '') {
                        $objectIdentifiers[$dtoClassName] = $identifierPropertyName;
                        $identifiersCount++;
                    } else {
                        throw new MappingCreationException('The Identifier attribute cannot be used without a property name when used as a Class attribute');
                    }
                    break;

                case NameTransformation::class:
                    try {
                        /** @var NameTransformation $transformationInstance */
                        $transformationInstance = $this->newMappingAttributeInstance($attribute);
                        $transformation = $transformationInstance;
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
            foreach ($this->getPropertyMappingAttributes($dtoClassName, $propertyName, $reflectionParameter->getAttributes()) as $attribute) {
                if ($attribute['name'] === ReferenceArray::class || $attribute['name'] === ScalarArray::class) {
                    $mappingArgumentName = $attribute['name'] === ReferenceArray::class
                        ? 'referenceClassName'
                        : 'mappedPropertyName';
                    $mappedProperty = $this->getStringMappingArgument(
                        $attribute,
                        $mappingArgumentName,
                        sprintf('property "%s::$%s"', $dtoClassName, $propertyName)
                    );

                    if($mappedProperty === null) {
                        if ($attribute['name'] === ReferenceArray::class) {
                            throw new MappingCreationException(sprintf(
                                'Invalid ReferenceArray attribute for %s::$%s; requires a mapped value.',
                                $dtoClassName,
                                $propertyName
                            ));
                        }
                        throw new MappingCreationException(sprintf(
                            'Invalid ScalarArray attribute for %s::$%s; requires a mapped value.',
                            $dtoClassName,
                            $propertyName
                        ));
                    }

                    if($this->validateMapping) {
                        if((new ReflectionProperty($dtoClassName, $propertyName))->isReadOnly()) {
                            throw new MappingCreationException($reflectionClass->getName().': property '.$propertyName.' cannot be readonly as it is non-scalar and '.static::class.' needs to access it after object instantiation.');
                        }
                    }
                    $objectsMapping[$dtoClassName][$propertyName] = $mappedProperty;
                    if($attribute['name'] === ReferenceArray::class) {
                        if(!class_exists($mappedProperty)) {
                            throw new MappingCreationException(sprintf(
                                'Invalid reference class "%s" configured on property "%s::$%s". %s is not a valid class name.',
                                $mappedProperty,
                                $dtoClassName,
                                $propertyName,
                                $mappedProperty
                            ));
                        }

                        /** @var class-string $mappedProperty */
                        $this->createMappingRecursive($mappedProperty, $objectIdentifiers, $objectsMapping, $mappingPath);
                    }
                    continue 2;
                } else if ($attribute['name'] === Identifier::class) {
                    $identifiersCount++;
                    $isIdentifier = true;
                    $identifierColumnName = $this->getStringMappingArgument(
                        $attribute,
                        'mappedPropertyName',
                        sprintf('property "%s::$%s"', $dtoClassName, $propertyName)
                    );
                    if($identifierColumnName === '') {
                        throw new MappingCreationException('Invalid Identifier attribute for '.$dtoClassName.'::$'.$propertyName);
                    }
                    if($identifierColumnName !== null) {
                        $columnName = $identifierColumnName;
                    }
                } else if ($attribute['name'] === Scalar::class) {
                    $scalarColumnName = $this->getStringMappingArgument(
                        $attribute,
                        'mappedPropertyName',
                        sprintf('property "%s::$%s"', $dtoClassName, $propertyName)
                    );
                    if($scalarColumnName === null || $scalarColumnName === '') {
                        throw new MappingCreationException('Invalid Scalar attribute for '.$dtoClassName.'::$'.$propertyName);
                    }
                    $columnName = $scalarColumnName;
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

    /**
     * @param ReflectionAttribute<object> $attribute
     */
    private function getAttributeArgument(ReflectionAttribute $attribute, string $argumentName): ?string
    {
        $arguments = $attribute->getArguments();

        if (array_key_exists($argumentName, $arguments)) {
            return $arguments[$argumentName] === null ? null : (string)$arguments[$argumentName];
        }

        if (array_key_exists(0, $arguments)) {
            return $arguments[0] === null ? null : (string)$arguments[0];
        }

        return null;
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
     * Keep cache keys deterministic while invalidating when YAML mapping changes.
     */
    private function createCacheKey(string $dtoClassName): string
    {
        $cacheKey = strtr($dtoClassName, ['\\' => '_', '-' => '_', ' ' => '_']);
        $mappingHash = md5(serialize($this->yamlMappings));

        return 'pixelshaped_flat_mapper_'.$cacheKey.'_'.$mappingHash;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     * @return list<array{
     *     name: class-string,
     *     arguments: array<int|string, mixed>,
     *     reflectionAttribute?: \ReflectionAttribute<object>
     * }>
     */
    private function getClassMappingAttributes(ReflectionClass $reflectionClass): array
    {
        return $this->mergeMappingAttributes(
            $reflectionClass->getName(),
            $reflectionClass->getAttributes()
        );
    }

    /**
     * @param list<\ReflectionAttribute<object>> $reflectionAttributes
     * @return list<array{
     *     name: class-string,
     *     arguments: array<int|string, mixed>,
     *     reflectionAttribute?: \ReflectionAttribute<object>
     * }>
     */
    private function getPropertyMappingAttributes(string $dtoClassName, string $propertyName, array $reflectionAttributes): array
    {
        return $this->mergeMappingAttributes(
            $dtoClassName,
            $reflectionAttributes,
            $propertyName
        );
    }

    /**
     * @param list<\ReflectionAttribute<object>> $reflectionAttributes
     * @return list<array{
     *     name: class-string,
     *     arguments: array<int|string, mixed>,
     *     reflectionAttribute?: \ReflectionAttribute<object>
     * }>
     */
    private function mergeMappingAttributes(string $dtoClassName, array $reflectionAttributes, ?string $propertyName = null): array
    {
        $mappingAttributes = [];

        foreach ($this->getYamlAttributes($dtoClassName, $propertyName) as $attributeName => $attributeArguments) {
            $mappingAttributes[$attributeName] = [
                'name' => $attributeName,
                'arguments' => $attributeArguments,
            ];
        }

        foreach ($reflectionAttributes as $reflectionAttribute) {
            $attributeName = $reflectionAttribute->getName();
            if (isset($mappingAttributes[$attributeName])) {
                // Ensure reflection attributes override YAML and keep declaration ordering.
                unset($mappingAttributes[$attributeName]);
            }
            $mappingAttributes[$attributeName] = [
                'name' => $attributeName,
                'arguments' => $reflectionAttribute->getArguments(),
                'reflectionAttribute' => $reflectionAttribute,
            ];
        }

        return array_values($mappingAttributes);
    }

    /**
     * @return array<class-string, array<int|string, mixed>>
     */
    private function getYamlAttributes(string $dtoClassName, ?string $propertyName = null): array
    {
        if (!isset($this->yamlMappings[$dtoClassName])) {
            return [];
        }

        $classMapping = $this->yamlMappings[$dtoClassName];
        if (!is_array($classMapping)) {
            throw new MappingCreationException(sprintf(
                'Invalid YAML mapping for class "%s". Expected an array.',
                $dtoClassName
            ));
        }

        if ($propertyName === null) {
            $rawAttributes = $classMapping['class'] ?? [];
            return $this->normalizeYamlAttributeMap(
                $rawAttributes,
                sprintf('class "%s"', $dtoClassName)
            );
        }

        $rawProperties = $classMapping['properties'] ?? [];
        if (!is_array($rawProperties)) {
            throw new MappingCreationException(sprintf(
                'Invalid YAML mapping for class "%s". The "properties" section must be an array.',
                $dtoClassName
            ));
        }

        $rawAttributes = $rawProperties[$propertyName] ?? [];
        return $this->normalizeYamlAttributeMap(
            $rawAttributes,
            sprintf('property "%s::$%s"', $dtoClassName, $propertyName)
        );
    }

    /**
     * @return array<class-string, array<int|string, mixed>>
     */
    private function normalizeYamlAttributeMap(mixed $rawAttributes, string $mappingTarget): array
    {
        if ($rawAttributes === null) {
            return [];
        }

        if (!is_array($rawAttributes)) {
            throw new MappingCreationException(sprintf(
                'Invalid YAML mapping for %s. Expected an attribute map array.',
                $mappingTarget
            ));
        }

        $normalizedAttributes = [];
        foreach ($rawAttributes as $attributeName => $rawArguments) {
            if (!is_string($attributeName)) {
                throw new MappingCreationException(sprintf(
                    'Invalid YAML mapping for %s. Attribute names must be strings.',
                    $mappingTarget
                ));
            }

            $resolvedAttributeName = $this->resolveAttributeClassName($attributeName, $mappingTarget);
            $normalizedAttributes[$resolvedAttributeName] = $this->normalizeYamlAttributeArguments(
                $rawArguments,
                $attributeName,
                $mappingTarget
            );
        }

        return $normalizedAttributes;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function normalizeYamlAttributeArguments(mixed $rawArguments, string $attributeName, string $mappingTarget): array
    {
        if ($rawArguments === null) {
            return [];
        }

        if (is_scalar($rawArguments)) {
            return [$rawArguments];
        }

        if (is_array($rawArguments)) {
            return $rawArguments;
        }

        throw new MappingCreationException(sprintf(
            'Invalid YAML mapping for attribute "%s" on %s. Expected null, scalar, or array arguments.',
            $attributeName,
            $mappingTarget
        ));
    }

    /**
     * @return class-string
     */
    private function resolveAttributeClassName(string $attributeName, string $mappingTarget): string
    {
        $className = str_contains($attributeName, '\\')
            ? $attributeName
            : self::MAPPING_NAMESPACE_PREFIX.$attributeName;

        if (!class_exists($className)) {
            throw new MappingCreationException(sprintf(
                'Invalid YAML mapping for %s. Attribute class "%s" does not exist.',
                $mappingTarget,
                $className
            ));
        }

        return $className;
    }

    /**
     * @param array{
     *     name: class-string,
     *     arguments: array<int|string, mixed>,
     *     reflectionAttribute?: \ReflectionAttribute<object>
     * } $attribute
     */
    private function getStringMappingArgument(array $attribute, string $argumentName, string $mappingTarget): ?string
    {
        if (isset($attribute['reflectionAttribute'])) {
            $argument = $this->getAttributeArgument($attribute['reflectionAttribute'], $argumentName);
        } elseif (array_key_exists(0, $attribute['arguments'])) {
            $argument = $attribute['arguments'][0];
        } elseif (array_key_exists($argumentName, $attribute['arguments'])) {
            $argument = $attribute['arguments'][$argumentName];
        } else {
            $argument = null;
        }

        if ($argument === null) {
            return null;
        }

        if (!is_string($argument)) {
            throw new MappingCreationException(sprintf(
                'Invalid %s argument for attribute "%s" on %s. Expected string, got %s.',
                $argumentName,
                $attribute['name'],
                $mappingTarget,
                get_debug_type($argument)
            ));
        }

        return $argument;
    }

    /**
     * @param array{
     *     name: class-string,
     *     arguments: array<int|string, mixed>,
     *     reflectionAttribute?: \ReflectionAttribute<object>
     * } $attribute
     */
    private function newMappingAttributeInstance(array $attribute): object
    {
        if (isset($attribute['reflectionAttribute'])) {
            return $attribute['reflectionAttribute']->newInstance();
        }

        $attributeClassName = $attribute['name'];
        return new $attributeClassName(...$attribute['arguments']);
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
                if ($row[$identifier] === null) {
                    continue;
                }

                $objectIdentifier = $row[$identifier];
                $isNewObject = !isset($objectsMap[$objectClass][$objectIdentifier]);
                $constructorValues = [];

                foreach ($this->objectsMapping[$dtoClassName][$objectClass] as $objectProperty => $foreignObjectClassOrIdentifier) {
                    if($foreignObjectClassOrIdentifier !== null) {
                        if (isset($this->objectsMapping[$dtoClassName][$foreignObjectClassOrIdentifier])) {
                            // Handles ReferenceArray attribute
                            $foreignIdentifier = $this->objectIdentifiers[$dtoClassName][$foreignObjectClassOrIdentifier];
                            if (!array_key_exists($foreignIdentifier, $row)) {
                                throw new MappingException('Identifier not found: ' . $foreignIdentifier);
                            }
                            if($row[$foreignIdentifier] !== null) {
                                $referencesMap[$objectClass][$objectIdentifier][$objectProperty][$row[$foreignIdentifier]] = $objectsMap[$foreignObjectClassOrIdentifier][$row[$foreignIdentifier]];
                            }
                        } else {
                            // Handles ScalarArray attribute
                            if(!array_key_exists($foreignObjectClassOrIdentifier, $row)) {
                                throw new MappingException('Data does not contain required property: ' . $foreignObjectClassOrIdentifier);
                            }
                            if($row[$foreignObjectClassOrIdentifier] !== null) {
                                $referencesMap[$objectClass][$objectIdentifier][$objectProperty][] = $row[$foreignObjectClassOrIdentifier];
                            }
                        }
                        if ($isNewObject) {
                            $constructorValues[] = [];
                        }
                    } else if ($isNewObject) {
                        if(!array_key_exists($objectProperty, $row)) {
                            throw new MappingException('Data does not contain required property: ' . $objectProperty);
                        }
                        $constructorValues[] = $row[$objectProperty];
                    }
                }

                if ($isNewObject) {
                    try {
                        $objectsMap[$objectClass][$objectIdentifier] = new $objectClass(...$constructorValues);
                    } catch (TypeError $e) {
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
