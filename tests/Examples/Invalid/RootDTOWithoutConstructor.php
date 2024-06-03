<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;
use Pixelshaped\FlatMapperBundle\Attributes\ReferencesArray;
use Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\LeafDTO;

class RootDTOWithoutConstructor
{
    #[Identifier]
    #[InboundPropertyName('object1_id')]
    public int $id;

    #[InboundPropertyName('object1_name')]
    public string $name;
}
