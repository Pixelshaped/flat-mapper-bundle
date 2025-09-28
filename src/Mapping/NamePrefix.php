<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class NamePrefix
{
    public function __construct(
        public string $prefix
    ) {}
}
