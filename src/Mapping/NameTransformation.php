<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class NameTransformation
{
    public readonly string $columnPrefix;
    public readonly bool $snakeCaseColumns;

    public function __construct(
        // New parameter names (recommended)
        string $columnPrefix = '',
        bool $snakeCaseColumns = false,

        // Old parameter names (deprecated but still supported for backward compatibility)
        /** @deprecated Use $columnPrefix instead */
        string $removePrefix = '',
        /** @deprecated Use $snakeCaseColumns instead */
        bool $camelize = false
    ) {
        // Use new names if provided, otherwise fall back to old names for backward compatibility
        $this->columnPrefix = $columnPrefix ?: $removePrefix;
        $this->snakeCaseColumns = $snakeCaseColumns ?: $camelize;
    }
}
