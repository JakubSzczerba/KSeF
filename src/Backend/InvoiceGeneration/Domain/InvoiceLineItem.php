<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\InvoiceGeneration\Domain;

final readonly class InvoiceLineItem
{
    public function __construct(
        public string $description,
        public float $quantity,
        public float $unitPriceNet,
        public int $vatRate,
    ) {}

    public function netValue(): float
    {
        return round($this->quantity * $this->unitPriceNet, 2);
    }

    public function vatAmount(): float
    {
        return round($this->netValue() * $this->vatRate / 100, 2);
    }

    public function grossValue(): float
    {
        return round($this->netValue() + $this->vatAmount(), 2);
    }
}
