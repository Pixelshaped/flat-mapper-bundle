<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle;

use Error;
use Pixelshaped\FlatMapperBundle\Exception\MappingCreationException;
use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;
use Pixelshaped\FlatMapperBundle\Mapping\ScalarArray;
use ReflectionClass;
use ReflectionProperty;

final class MappingResolver
{
    // Pre-compiled regex patterns for better performance
    private const SNAKE_CASE_PATTERN_1 = '/([A-Z]+)([A-Z][a-z])/';
    private const SNAKE_CASE_PATTERN_2 = '/([a-z\d])([A-Z])/';
    private const SNAKE_CASE_REPLACEMENT = '\\1_\\2';
    private const MAPPING_NAMESPACE_PREFIX = 'Pixelshaped\\FlatMapperBundle\\Mapping\\';

    private bool $validateMapping = true;

    /**
     * @var array<class-string, array{
     *     class?: array<string, mixed>,
     *     properties?: array<string, array<string, mixed>>
     * }>
     */
    private array $yamlMappings = [];

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
    }

    public function getCacheSignature(string $dtoClassName): string
    {
        return md5(serialize($this->yamlMappings[$dtoClassName] ?? []));
    }

    /**
     * @param class-string $dtoClassName
     * @return array{'objectIdentifiers': array<class-string, string>, "objectsMapping": array<class-string, array<int|string, null|string>>}
     */
    public function resolve(string $dtoClassName): array
    {
        if(!class_exists($dtoClassName)) {
            throw new MappingCreationException($dtoClassName.' is not a valid class name');
        }

        return $this->createMappingRecursive($dtoClassName);
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

        foreach ($this->getClassMappingAttributes($reflectionClass) as $attribute) {
            switch ($attribute['name']) {
                case Identifier::class:
                    $identifierPropertyName = $this->getStringMappingArgument(
                        $attribute,
                        'mappedPropertyName',
                        sprintf('class "%s"', $dtoClassName)
                    );
                    if ($identifierPropertyName !== null) {
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
                        throw new MappingCreationException(sprintf(
                            'Attribute "%s" on property "%s::$%s" requires a mapped value.',
                            $attribute['name'],
                            $dtoClassName,
                            $propertyName
                        ));
                    }

                    if($this->validateMapping) {
                        if((new ReflectionProperty($dtoClassName, $propertyName))->isReadOnly()) {
                            throw new MappingCreationException($reflectionClass->getName().': property '.$propertyName.' cannot be readonly as it is non-scalar and '.FlatMapper::class.' needs to access it after object instantiation.');
                        }
                    }
                    $objectsMapping[$dtoClassName][$propertyName] = $mappedProperty;
                    if($attribute['name'] === ReferenceArray::class) {
                        if(!class_exists($mappedProperty)) {
                            throw new MappingCreationException(sprintf(
                                'Invalid reference class "%s" configured on property "%s::$%s".',
                                $mappedProperty,
                                $dtoClassName,
                                $propertyName
                            ));
                        }

                        /** @var class-string $mappedProperty */
                        $this->createMappingRecursive($mappedProperty, $objectIdentifiers, $objectsMapping);
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
                    if($identifierColumnName !== null) {
                        $columnName = $identifierColumnName;
                    }
                } else if ($attribute['name'] === Scalar::class) {
                    $scalarColumnName = $this->getStringMappingArgument(
                        $attribute,
                        'mappedPropertyName',
                        sprintf('property "%s::$%s"', $dtoClassName, $propertyName)
                    );
                    if($scalarColumnName !== null) {
                        $columnName = $scalarColumnName;
                    }
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
            foreach ($objectIdentifiers as $value) {
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
        if (array_key_exists(0, $attribute['arguments'])) {
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
}
