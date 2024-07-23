<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final readonly class Identifier
{
    public function __construct(
        public ?string $mappedPropertyName = null
    ) {}
}
