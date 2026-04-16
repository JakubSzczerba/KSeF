<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Backend\InvoiceGeneration;

use Ksef\Backend\InvoiceGeneration\Application\GenerateInvoiceXml\GenerateInvoiceXmlCommand;
use Ksef\Backend\InvoiceGeneration\Application\GenerateInvoiceXml\GenerateInvoiceXmlHandler;
use Ksef\Backend\InvoiceGeneration\Domain\InvoiceLineItem;
use Ksef\Backend\InvoiceGeneration\Infrastructure\XmlBuilder\Fa3XmlBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GenerateInvoiceXmlHandlerTest extends TestCase
{
    #[Test]
    public function shouldReturnXmlStringForValidCommand(): void
    {
        $handler = new GenerateInvoiceXmlHandler(new Fa3XmlBuilder());

        $command = new GenerateInvoiceXmlCommand(
            invoiceNumber: 'FV/2026/01/001',
            invoiceDate: '2026-01-15',
            saleDate: '2026-01-15',
            paymentDueDate: '2026-02-14',
            sellerNip: '1234567890',
            sellerName: 'Firma Testowa Sp. z o.o.',
            sellerAddress: 'ul. Testowa 1, 00-001 Warszawa',
            bankAccount: 'PL12345678901234567890123456',
            buyerNip: '0987654321',
            buyerName: 'Nabywca Testowy Sp. z o.o.',
            buyerAddress: 'ul. Kupiecka 5, 00-002 Warszawa',
            lines: [new InvoiceLineItem('Usługa programistyczna', 1.0, 5000.00, 23)],
        );

        $xml = $handler->handle($command);

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('FV/2026/01/001', $xml);
        self::assertStringContainsString('1234567890', $xml);
        self::assertStringContainsString('0987654321', $xml);
    }

    #[Test]
    public function shouldPassNotesToXmlBuilder(): void
    {
        $handler = new GenerateInvoiceXmlHandler(new Fa3XmlBuilder());

        $command = new GenerateInvoiceXmlCommand(
            invoiceNumber: 'FV/2026/01/002',
            invoiceDate: '2026-01-15',
            saleDate: '2026-01-15',
            paymentDueDate: '2026-02-14',
            sellerNip: '1234567890',
            sellerName: 'Firma Testowa',
            sellerAddress: 'ul. Testowa 1',
            bankAccount: 'PL12345678901234567890123456',
            buyerNip: '0987654321',
            buyerName: 'Nabywca',
            buyerAddress: 'ul. Kupiecka 5',
            lines: [new InvoiceLineItem('Towar', 2.0, 100.00, 23)],
            notes: 'Płatność przelewem',
        );

        $xml = $handler->handle($command);

        self::assertStringContainsString('Płatność przelewem', $xml);
    }
}
