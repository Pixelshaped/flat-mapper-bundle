<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ReferenceArray
{
    public function __construct(
        /** @var class-string */
        public string $referenceClassName
    ) {}
}
