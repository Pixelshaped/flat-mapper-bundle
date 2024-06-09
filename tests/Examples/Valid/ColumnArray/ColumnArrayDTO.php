<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ColumnArray;

use Pixelshaped\FlatMapperBundle\Attributes\ColumnArray;
use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;

class ColumnArrayDTO
{
    /**
     * @param array<int> $object2s
     */
    public function __construct(
        #[Identifier]
        #[InboundPropertyName('object1_id')]
        public int $id,
        #[InboundPropertyName('object1_name')]
        public string $name,
        #[ColumnArray('object2_id')]
        public array $object2s,
    ) {}
}
