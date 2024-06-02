<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;

class LeafDTO
{
    public function __construct(
        #[Identifier]
        #[InboundPropertyName('object1_id')]
        public int $id,
        #[InboundPropertyName('object2_name')]
        public string $name,
        #[InboundPropertyName('object2_value')]
        public string $value,
    ) {}
}
