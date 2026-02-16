<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

final class RootDTOWithNonStringScalarArgument
{
    public function __construct(
        #[Identifier]
        // @phpstan-ignore-next-line
        #[Scalar(123)]
        public int $id,
    ) {
    }
}
