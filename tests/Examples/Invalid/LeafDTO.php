<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class LeafDTO
{
    public function __construct(
        #[Identifier]
        #[Scalar('object1_id')]
        public int $id,
        #[Scalar('object2_name')]
        public string $name,
        #[Scalar('object2_value')]
        public string $value,
    ) {}
}
