<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ColumnArray
{
    public function __construct(
        public string $inboundPropertyName
    ) {}
}
