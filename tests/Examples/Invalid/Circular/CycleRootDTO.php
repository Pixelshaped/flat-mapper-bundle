<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\Circular;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;

final class CycleRootDTO
{
    /**
     * @param array<CycleLeafDTO> $leafs
     */
    public function __construct(
        #[Identifier]
        public int $rootId,
        #[ReferenceArray(CycleLeafDTO::class)]
        public array $leafs
    ) {
    }
}
