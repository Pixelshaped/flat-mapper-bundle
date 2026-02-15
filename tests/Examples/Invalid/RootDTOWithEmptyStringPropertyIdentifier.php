<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class RootDTOWithEmptyStringPropertyIdentifier
{
    public function __construct(
        #[Identifier('')]
        #[Scalar('object1_id')]
        public int $id,
    ) {}
}
