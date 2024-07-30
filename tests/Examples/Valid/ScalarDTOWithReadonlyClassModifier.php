<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

/**
 * You can use a readonly property/class modifier on your scalar DTOs as FlatMapper's object linker
 * doesn't need to access them after object instantiation
 */
readonly class ScalarDTOWithReadonlyClassModifier
{
    public function __construct(
        #[Identifier]
        #[Scalar('object1_id')]
        public int $id,
        #[Scalar('object1_name')]
        public string $name,
    ) {}
}
