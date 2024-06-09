<?php

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Complex;

use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;
use Pixelshaped\FlatMapperBundle\Attributes\ReferencesArray;

class CustomerDTO
{
    public function __construct(
        #[Identifier('customer_id')]
        private int $id,
        #[InboundPropertyName('customer_name')]
        private string $name,
        /** @var array<InvoiceDTO> */
        #[ReferencesArray(InvoiceDTO::class)]
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