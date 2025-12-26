<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

#[NameTransformation(snakeCaseColumns: true)]
final readonly class ItemDTO
{
    public function __construct(
        #[Identifier]
        public int $itemId,
        public string $itemName,
        public float $itemPrice
    ) {
    }
}
