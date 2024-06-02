<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class InboundPropertyName
{
    public function __construct(public string $propertyName)
    {
    }
}
