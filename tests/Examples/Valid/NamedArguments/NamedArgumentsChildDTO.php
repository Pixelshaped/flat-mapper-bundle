<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NamedArguments;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

final class NamedArgumentsChildDTO
{
    public function __construct(
        #[Identifier(mappedPropertyName: 'child_id')]
        public int $id,
        #[Scalar(mappedPropertyName: 'child_name')]
        public string $name,
    ) {}
}
