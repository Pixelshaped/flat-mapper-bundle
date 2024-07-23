<?php

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Complex;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class CustomerDTO
{
    public function __construct(
        #[Identifier('customer_id')]
        private int $id,
        #[Scalar('customer_name')]
        private string $name,
        /** @var array<InvoiceDTO> */
        #[ReferenceArray(InvoiceDTO::class)]
        private array $invoices,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<InvoiceDTO>
     */
    public function getInvoices(): array
    {
        return $this->invoices;
    }

    public function getTotalPurchases(): int
    {
        $totalPurchases = 0;
        foreach ($this->getInvoices() as $invoice) {
            $totalPurchases += $invoice->getTotalPrice();
        }
        return $totalPurchases;
    }
}
