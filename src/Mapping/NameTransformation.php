<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class NameTransformation
{
    public function __construct(
        public string $removePrefix = '',
        public bool $camelize = false
    ) {
    }
}
