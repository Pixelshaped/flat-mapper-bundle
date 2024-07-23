<?php

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Complex;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

// We don't intend to use the product_id property, so we use the Identifier attribute as
// a class attribute. It will then only be used by FlatMapper internally to keep track
// of ProductDTO instances creation (one instance per Identifier).
#[Identifier('product_id')]
class ProductDTO
{
    public function __construct(
        #[Scalar('product_sku')]
        private int $sku,
        #[Scalar('product_weight')]
        private float $weight,
        #[Scalar('product_price')]
        private int $price,
        #[Scalar('product_quantity')]
        private int $quantity,
    ) {}

    public function getSku(): int
    {
        return $this->sku;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getTotalWeight(): float
    {
        return $this->getWeight() * $this->getQuantity();
    }

    public function getTotalPrice(): int
    {
        return $this->getPrice() * $this->getQuantity();
    }
}
