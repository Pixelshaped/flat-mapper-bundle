<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

#[NameTransformation(snakeCaseColumns: true)]
final readonly class ProductDTO
{
    public function __construct(
        #[Identifier]
        public int $ProductId,
        public string $ProductName,
        public float $ProductPrice
    ) {
    }
}
