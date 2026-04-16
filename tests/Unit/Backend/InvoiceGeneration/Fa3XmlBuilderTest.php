<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Backend\InvoiceGeneration;

use DOMDocument;
use Ksef\Backend\InvoiceGeneration\Domain\InvoiceLineItem;
use Ksef\Backend\InvoiceGeneration\Domain\InvoiceToGenerate;
use Ksef\Backend\InvoiceGeneration\Infrastructure\XmlBuilder\Fa3XmlBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Fa3XmlBuilderTest extends TestCase
{
    private Fa3XmlBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new Fa3XmlBuilder();
    }

    #[Test]
    public function shouldProduceWellFormedXmlDocument(): void
    {
        $invoice = $this->buildInvoice();
        $xml = $this->builder->build($invoice);

        $doc = new DOMDocument();
        $result = $doc->loadXML($xml);

        self::assertTrue($result, 'Generated XML must be well-formed');
    }

    #[Test]
    public function shouldContainRequiredFa3StructuralElements(): void
    {
        $xml = $this->builder->build($this->buildInvoice());

        self::assertStringContainsString('Naglowek', $xml);
        self::assertStringContainsString('Podmiot1', $xml);
        self::assertStringContainsString('Podmiot2', $xml);
        self::assertStringContainsString('FA (3)', $xml);
        self::assertStringContainsString('RodzajFaktury', $xml);
        self::assertStringContainsString('VAT', $xml);
        self::assertStringContainsString('Adnotacje', $xml);
        self::assertStringContainsString('Platnosc', $xml);
    }

    #[Test]
    public function shouldIncludeSellerNip(): void
    {
        $xml = $this->builder->build($this->buildInvoice(sellerNip: '1111111111'));

        self::assertStringContainsString('1111111111', $xml);
    }

    #[Test]
    public function shouldIncludeBuyerNip(): void
    {
        $xml = $this->builder->build($this->buildInvoice(buyerNip: '2222222222'));

        self::assertStringContainsString('2222222222', $xml);
    }

    #[Test]
    public function shouldIncludeInvoiceNumber(): void
    {
        $xml = $this->builder->build($this->buildInvoice(invoiceNumber: 'FV/2026/04/007'));

        self::assertStringContainsString('FV/2026/04/007', $xml);
    }

    #[Test]
    public function shouldIncludeInvoiceDate(): void
    {
        $xml = $this->builder->build($this->buildInvoice(invoiceDate: '2026-04-16'));

        self::assertStringContainsString('2026-04-16', $xml);
    }

    #[Test]
    public function shouldCalculateGrossTotalFor23PercentVat(): void
    {
        // 1 × 100.00 netto, 23% VAT → brutto = 123.00
        $line = new InvoiceLineItem('Usługa', 1.0, 100.00, 23);
        $xml = $this->builder->build($this->buildInvoice(lines: [$line]));

        self::assertMatchesRegularExpression('/<tns:P_15[^>]*>123\.00<\/tns:P_15>/', $xml);
    }

    #[Test]
    public function shouldCalculateGrossTotalForMultipleLines(): void
    {
        // 2 × 50.00 netto 23% = 100.00 netto + 23.00 VAT = 123.00 brutto
        // 1 × 200.00 netto 8% = 200.00 netto + 16.00 VAT = 216.00 brutto
        // łącznie brutto = 339.00
        $lines = [
            new InvoiceLineItem('Usługa A', 2.0, 50.00, 23),
            new InvoiceLineItem('Usługa B', 1.0, 200.00, 8),
        ];
        $xml = $this->builder->build($this->buildInvoice(lines: $lines));

        self::assertMatchesRegularExpression('/<tns:P_15[^>]*>339\.00<\/tns:P_15>/', $xml);
    }

    #[Test]
    public function shouldIncludeLineItemDescription(): void
    {
        $line = new InvoiceLineItem('Programowanie PHP', 3.0, 200.00, 23);
        $xml = $this->builder->build($this->buildInvoice(lines: [$line]));

        self::assertStringContainsString('Programowanie PHP', $xml);
    }

    #[Test]
    public function shouldIncludeAllLineItemsAsRows(): void
    {
        $lines = [
            new InvoiceLineItem('Pozycja 1', 1.0, 100.00, 23),
            new InvoiceLineItem('Pozycja 2', 2.0, 50.00, 23),
            new InvoiceLineItem('Pozycja 3', 1.0, 300.00, 8),
        ];
        $xml = $this->builder->build($this->buildInvoice(lines: $lines));

        self::assertSame(3, substr_count($xml, '<tns:FaWiersz>'));
    }

    #[Test]
    public function shouldIncludeNotes(): void
    {
        $xml = $this->builder->build($this->buildInvoice(notes: 'Uwaga: termin 14 dni'));

        self::assertStringContainsString('DodatkowyOpis', $xml);
        self::assertStringContainsString('Uwaga: termin 14 dni', $xml);
    }

    #[Test]
    public function shouldOmitDodatkowyOpisWhenNoNotes(): void
    {
        $xml = $this->builder->build($this->buildInvoice(notes: null));

        self::assertStringNotContainsString('DodatkowyOpis', $xml);
    }

    #[Test]
    public function shouldIncludePaymentDueDateInTerminPlatnosci(): void
    {
        $xml = $this->builder->build($this->buildInvoice(paymentDueDate: '2026-05-30'));

        self::assertStringContainsString('TerminPlatnosci', $xml);
        self::assertStringContainsString('2026-05-30', $xml);
    }

    /**
     * @param InvoiceLineItem[] $lines
     */
    private function buildInvoice(
        string $sellerNip = '1234567890',
        string $buyerNip = '0987654321',
        string $invoiceNumber = 'FV/2026/01/001',
        string $invoiceDate = '2026-01-15',
        string $paymentDueDate = '2026-02-14',
        ?string $notes = null,
        array $lines = [],
    ): InvoiceToGenerate {
        if ([] === $lines) {
            $lines = [new InvoiceLineItem('Usługa testowa', 1.0, 100.00, 23)];
        }

        return new InvoiceToGenerate(
            invoiceNumber: $invoiceNumber,
            invoiceDate: $invoiceDate,
            saleDate: $invoiceDate,
            paymentDueDate: $paymentDueDate,
            sellerNip: $sellerNip,
            sellerName: 'Firma Testowa Sp. z o.o.',
            sellerAddress: 'ul. Testowa 1, 00-001 Warszawa',
            bankAccount: 'PL12345678901234567890123456',
            buyerNip: $buyerNip,
            buyerName: 'Nabywca Testowy Sp. z o.o.',
            buyerAddress: 'ul. Kupiecka 5, 00-002 Warszawa',
            lines: $lines,
            notes: $notes,
        );
    }
}
