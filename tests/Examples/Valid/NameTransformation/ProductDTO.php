<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

#[Identifier('product_id')]
#[NameTransformation(camelize: true)]
final readonly class ProductDTO
{
    public function __construct(
        public int $ProductId,
        public string $ProductName,
        public float $ProductPrice
    ) {
    }
}
