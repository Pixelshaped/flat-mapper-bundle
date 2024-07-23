<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ScalarArray
{
    public function __construct(
        public string $mappedPropertyName
    ) {}
}
