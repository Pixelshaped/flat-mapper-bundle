<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ReferencesArray
{
    public function __construct(
        /** @var class-string */
        public string $className
    ) {}
}
