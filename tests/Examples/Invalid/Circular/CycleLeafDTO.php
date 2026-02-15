<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\Circular;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;

final class CycleLeafDTO
{
    /**
     * @param array<CycleRootDTO> $roots
     */
    public function __construct(
        #[Identifier]
        public int $leafId,
        #[ReferenceArray(CycleRootDTO::class)]
        public array $roots
    ) {
    }
}
