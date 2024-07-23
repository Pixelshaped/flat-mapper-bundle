<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;

class WithoutAttributeDTO
{
    public function __construct(
        #[Identifier]
        public int $id,
        public string $foo,
        public int $bar,
    ) {}
}
