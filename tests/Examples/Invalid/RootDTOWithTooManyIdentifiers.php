<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class RootDTOWithTooManyIdentifiers
{
    /**
     * @param array<LeafDTO> $leafs
     */
    public function __construct(
        #[Identifier]
        #[Scalar('object1_id')]
        public int $id,
        #[Identifier]
        #[Scalar('object1_name')]
        public string $name,
        #[ReferenceArray(LeafDTO::class)]
        public array $leafs,
    ) {}
}
