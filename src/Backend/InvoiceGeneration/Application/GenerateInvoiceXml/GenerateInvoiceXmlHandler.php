<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\InvoiceGeneration\Application\GenerateInvoiceXml;

use Ksef\Backend\InvoiceGeneration\Domain\InvoiceToGenerate;
use Ksef\Backend\InvoiceGeneration\Infrastructure\XmlBuilder\Fa3XmlBuilder;

final class GenerateInvoiceXmlHandler
{
    public function __construct(
        private readonly Fa3XmlBuilder $xmlBuilder
    ) {}

    public function handle(GenerateInvoiceXmlCommand $command): string
    {
        $invoice = new InvoiceToGenerate(
            invoiceNumber: $command->invoiceNumber,
            invoiceDate: $command->invoiceDate,
            saleDate: $command->saleDate,
            paymentDueDate: $command->paymentDueDate,
            sellerNip: $command->sellerNip,
            sellerName: $command->sellerName,
            sellerAddress: $command->sellerAddress,
            bankAccount: $command->bankAccount,
            buyerNip: $command->buyerNip,
            buyerName: $command->buyerName,
            buyerAddress: $command->buyerAddress,
            lines: $command->lines,
            notes: $command->notes,
        );

        return $this->xmlBuilder->build($invoice);
    }
}
