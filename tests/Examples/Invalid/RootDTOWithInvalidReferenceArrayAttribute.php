<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class RootDTOWithInvalidReferenceArrayAttribute
{
    /**
     * @param array<int, object> $children
     */
    public function __construct(
        #[Identifier]
        #[Scalar('object1_id')]
        public int $id,
        // @phpstan-ignore-next-line
        #[ReferenceArray(invalidArgumentName: LeafDTO::class)]
        public array $children,
    ) {}
}
