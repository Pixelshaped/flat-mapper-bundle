<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ScalarArray;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;
use Pixelshaped\FlatMapperBundle\Mapping\ScalarArray;

class ScalarArrayDTO
{
    /**
     * @param array<int> $object2s
     */
    public function __construct(
        #[Identifier]
        #[Scalar('object1_id')]
        public int $id,
        #[Scalar('object1_name')]
        public string $name,
        #[ScalarArray('object2_id')]
        public array $object2s,
    ) {}
}
