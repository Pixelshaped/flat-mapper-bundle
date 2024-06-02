<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid;

use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;
use Pixelshaped\FlatMapperBundle\Attributes\ReferencesArray;

class RootDTO
{
    /**
     * @param array<LeafDTO> $leafs
     */
    public function __construct(
        #[Identifier]
        #[InboundPropertyName('object1_id')]
        public int $id,
        #[InboundPropertyName('object1_name')]
        public string $name,
        #[ReferencesArray(LeafDTO::class)]
        public array $leafs,
    ) {}
}
