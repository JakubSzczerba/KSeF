<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\InvoiceGeneration\Domain;

final readonly class InvoiceToGenerate
{
    /**
     * @param InvoiceLineItem[] $lines
     */
    public function __construct(
        public string $invoiceNumber,
        public string $invoiceDate,
        public string $saleDate,
        public string $paymentDueDate,
        public string $sellerNip,
        public string $sellerName,
        public string $sellerAddress,
        public string $bankAccount,
        public string $buyerNip,
        public string $buyerName,
        public string $buyerAddress,
        public array $lines,
        public ?string $notes = null,
    ) {}
}
