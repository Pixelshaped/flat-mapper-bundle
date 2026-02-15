<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

#[Identifier('object1_id')]
abstract class RootAbstractDTO
{
    public function __construct(
        #[Scalar('object1_name')]
        public string $name,
    ) {}
}

