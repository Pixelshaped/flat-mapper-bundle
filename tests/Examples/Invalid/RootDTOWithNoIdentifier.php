<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class RootDTOWithNoIdentifier
{
    /**
     * @param array<LeafDTO> $leafs
     */
    public function __construct(
        #[Scalar('object1_id')]
        public int $id,
        #[Scalar('object1_name')]
        public string $name,
        #[ReferenceArray(LeafDTO::class)]
        public array $leafs,
    ) {}
}
