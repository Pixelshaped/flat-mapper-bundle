<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class RootDTOWithoutConstructor
{
    #[Identifier]
    #[Scalar('object1_id')]
    public int $id;

    #[Scalar('object1_name')]
    public string $name;
}
