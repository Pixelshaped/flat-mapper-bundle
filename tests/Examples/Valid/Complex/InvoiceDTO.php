<?php

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Complex;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class InvoiceDTO
{
    public function __construct(
        #[Identifier('invoice_id')]
        private int $id,
        #[Scalar('invoice_vat')]
        private string $vat,
        #[Scalar('invoice_address')]
        private string $address,
        /** @var array<ProductDTO> */
        #[ReferenceArray(ProductDTO::class)]
        private array $products,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getVat(): string
    {
        return $this->vat;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @return array<ProductDTO>
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    public function getTotalWeight(): float
    {
        $totalWeight = 0;
        foreach ($this->getProducts() as $product) {
            $totalWeight += $product->getTotalWeight();
        }
        return $totalWeight;
    }

    public function getTotalPrice(): int
    {
        $totalPrice = 0;
        foreach ($this->getProducts() as $product) {
            $totalPrice += $product->getTotalPrice();
        }
        return $totalPrice;
    }
}
