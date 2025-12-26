<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

#[NameTransformation(removePrefix: 'order_', camelize: true)]
final readonly class OrderDTO
{
    public function __construct(
        #[Identifier]
        public int $Id,
        public string $CustomerName,
        public float $TotalAmount
    ) {
    }
}
