<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;
use Pixelshaped\FlatMapperBundle\Mapping\ScalarArray;

class RootDTOWithInvalidScalarArrayAttribute
{
    /**
     * @param array<int, string> $children
     */
    public function __construct(
        #[Identifier]
        #[Scalar('object1_id')]
        public int $id,
        // @phpstan-ignore-next-line
        #[ScalarArray(invalidArgumentName: 'object2_id')]
        public array $children,
    ) {}
}
