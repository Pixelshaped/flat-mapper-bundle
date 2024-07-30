<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

/**
 * You cannot use a readonly property/class modifier on DTOs that contain ReferenceArray or ScalarArray attributes
 * as FlatMapper will need to access those properties after instantiation
 */
readonly class RootDTOWithReadonlyClassModifier
{
    /**
     * @param array<LeafDTO> $leafs
     */
    public function __construct(
        #[Identifier]
        #[Scalar('object1_id')]
        public int $id,
        #[Scalar('object1_name')]
        public string $name,
        #[ReferenceArray(LeafDTO::class)]
        public array $leafs,
    ) {}
}
