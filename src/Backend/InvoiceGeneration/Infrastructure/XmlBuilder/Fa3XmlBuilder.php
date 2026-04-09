<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\InvoiceGeneration\Infrastructure\XmlBuilder;

use DOMDocument;
use DOMElement;
use Ksef\Backend\InvoiceGeneration\Domain\InvoiceLineItem;
use Ksef\Backend\InvoiceGeneration\Domain\InvoiceToGenerate;

final class Fa3XmlBuilder
{
    private const FA3_NAMESPACE = 'http://crd.gov.pl/wzor/2025/06/25/13775/';
    private const XSI_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    public function build(InvoiceToGenerate $invoice): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS(self::FA3_NAMESPACE, 'Faktura');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI_NAMESPACE);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tns', self::FA3_NAMESPACE);
        $root->setAttributeNS(
            self::XSI_NAMESPACE,
            'xsi:schemaLocation',
            self::FA3_NAMESPACE
        );
        $doc->appendChild($root);

        $this->appendHeader($doc, $root);
        $this->appendSeller($doc, $root, $invoice);
        $this->appendBuyer($doc, $root, $invoice);
        $this->appendFa($doc, $root, $invoice);

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Nie można wygenerować XML faktury.');
        }

        return $xml;
    }

    private function appendHeader(DOMDocument $doc, DOMElement $root): void
    {
        $naglowek = $this->el($doc, 'tns:Naglowek');

        $kod = $this->el($doc, 'tns:KodFormularza', 'FA');
        $kod->setAttribute('kodSystemowy', 'FA (3)');
        $kod->setAttribute('wersjaSchemy', '1-0E');
        $naglowek->appendChild($kod);

        $naglowek->appendChild($this->el($doc, 'tns:WariantFormularza', '3'));
        $naglowek->appendChild($this->el($doc, 'tns:DataWytworzeniaFa', (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z')));
        $naglowek->appendChild($this->el($doc, 'tns:SystemInfo', 'KSeF Dashboard'));

        $root->appendChild($naglowek);
    }

    private function appendSeller(DOMDocument $doc, DOMElement $root, InvoiceToGenerate $invoice): void
    {
        $podmiot = $this->el($doc, 'tns:Podmiot1');

        $dane = $this->el($doc, 'tns:DaneIdentyfikacyjne');
        $dane->appendChild($this->el($doc, 'tns:NIP', $invoice->sellerNip));
        $dane->appendChild($this->el($doc, 'tns:Nazwa', $invoice->sellerName));
        $podmiot->appendChild($dane);

        $adres = $this->el($doc, 'tns:Adres');
        $adres->appendChild($this->el($doc, 'tns:KodKraju', 'PL'));
        $adres->appendChild($this->el($doc, 'tns:AdresL1', $invoice->sellerAddress));
        $podmiot->appendChild($adres);

        $root->appendChild($podmiot);
    }

    private function appendBuyer(DOMDocument $doc, DOMElement $root, InvoiceToGenerate $invoice): void
    {
        $podmiot = $this->el($doc, 'tns:Podmiot2');

        $dane = $this->el($doc, 'tns:DaneIdentyfikacyjne');
        $dane->appendChild($this->el($doc, 'tns:NIP', $invoice->buyerNip));
        $dane->appendChild($this->el($doc, 'tns:Nazwa', $invoice->buyerName));
        $podmiot->appendChild($dane);

        $adres = $this->el($doc, 'tns:Adres');
        $adres->appendChild($this->el($doc, 'tns:KodKraju', 'PL'));
        $adres->appendChild($this->el($doc, 'tns:AdresL1', $invoice->buyerAddress));
        $podmiot->appendChild($adres);

        $root->appendChild($podmiot);
    }

    private function appendFa(DOMDocument $doc, DOMElement $root, InvoiceToGenerate $invoice): void
    {
        $fa = $this->el($doc, 'tns:Fa');

        $fa->appendChild($this->el($doc, 'tns:KodWaluty', 'PLN'));
        $fa->appendChild($this->el($doc, 'tns:P_1', $invoice->invoiceDate));
        $fa->appendChild($this->el($doc, 'tns:P_2', $invoice->invoiceNumber));
        $fa->appendChild($this->el($doc, 'tns:P_6', $invoice->saleDate));

        $totals = $this->calcTotals($invoice->lines);
        foreach ($totals['netByRate'] as $rate => $net) {
            $fa->appendChild($this->el($doc, 'tns:P_13_' . $this->rateIndex($rate), $this->fmt($net)));
        }
        foreach ($totals['vatByRate'] as $rate => $vat) {
            $fa->appendChild($this->el($doc, 'tns:P_14_' . $this->rateIndex($rate), $this->fmt($vat)));
        }
        $fa->appendChild($this->el($doc, 'tns:P_15', $this->fmt($totals['total'])));

        $fa->appendChild($this->buildAdnotacje($doc));
        $fa->appendChild($this->el($doc, 'tns:RodzajFaktury', 'VAT'));

        if (null !== $invoice->notes && $invoice->notes !== '') {
            $desc = $this->el($doc, 'tns:DodatkowyOpis');
            $desc->appendChild($this->el($doc, 'tns:Klucz', 'Uwagi'));
            $desc->appendChild($this->el($doc, 'tns:Wartosc', $invoice->notes));
            $fa->appendChild($desc);
        }

        foreach ($invoice->lines as $i => $line) {
            $fa->appendChild($this->buildLine($doc, $line, $i + 1));
        }

        $fa->appendChild($this->buildPlatnosc($doc, $invoice));

        $root->appendChild($fa);
    }

    private function buildAdnotacje(DOMDocument $doc): DOMElement
    {
        $ann = $this->el($doc, 'tns:Adnotacje');
        $ann->appendChild($this->el($doc, 'tns:P_16', '2'));
        $ann->appendChild($this->el($doc, 'tns:P_17', '2'));
        $ann->appendChild($this->el($doc, 'tns:P_18', '2'));
        $ann->appendChild($this->el($doc, 'tns:P_18A', '2'));

        $zwolnienie = $this->el($doc, 'tns:Zwolnienie');
        $zwolnienie->appendChild($this->el($doc, 'tns:P_19N', '1'));
        $ann->appendChild($zwolnienie);

        $nst = $this->el($doc, 'tns:NoweSrodkiTransportu');
        $nst->appendChild($this->el($doc, 'tns:P_22N', '1'));
        $ann->appendChild($nst);

        $ann->appendChild($this->el($doc, 'tns:P_23', '2'));

        $pmarzy = $this->el($doc, 'tns:PMarzy');
        $pmarzy->appendChild($this->el($doc, 'tns:P_PMarzyN', '1'));
        $ann->appendChild($pmarzy);

        return $ann;
    }

    private function buildLine(DOMDocument $doc, InvoiceLineItem $line, int $nr): DOMElement
    {
        $row = $this->el($doc, 'tns:FaWiersz');
        $row->appendChild($this->el($doc, 'tns:NrWierszaFa', (string) $nr));
        $row->appendChild($this->el($doc, 'tns:P_7', $line->description));
        $row->appendChild($this->el($doc, 'tns:P_8B', $this->fmt($line->quantity)));
        $row->appendChild($this->el($doc, 'tns:P_9A', $this->fmt($line->unitPriceNet)));
        $row->appendChild($this->el($doc, 'tns:P_11', $this->fmt($line->netValue())));
        $row->appendChild($this->el($doc, 'tns:P_12', (string) $line->vatRate));

        return $row;
    }

    private function buildPlatnosc(DOMDocument $doc, InvoiceToGenerate $invoice): DOMElement
    {
        $platnosc = $this->el($doc, 'tns:Platnosc');

        $termin = $this->el($doc, 'tns:TerminPlatnosci');
        $termin->appendChild($this->el($doc, 'tns:Termin', $invoice->paymentDueDate));
        $platnosc->appendChild($termin);

        $platnosc->appendChild($this->el($doc, 'tns:FormaPlatnosci', '6'));

        $rb = $this->el($doc, 'tns:RachunekBankowy');
        $rb->appendChild($this->el($doc, 'tns:NrRB', preg_replace('/\s+/', '', $invoice->bankAccount) ?? $invoice->bankAccount));
        $platnosc->appendChild($rb);

        return $platnosc;
    }

    /**
     * @param InvoiceLineItem[] $lines
     * @return array{netByRate: array<int,float>, vatByRate: array<int,float>, total: float}
     */
    private function calcTotals(array $lines): array
    {
        $netByRate = [];
        $vatByRate = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $rate = $line->vatRate;
            $netByRate[$rate] = round(($netByRate[$rate] ?? 0.0) + $line->netValue(), 2);
            $vatByRate[$rate] = round(($vatByRate[$rate] ?? 0.0) + $line->vatAmount(), 2);
            $total = round($total + $line->grossValue(), 2);
        }

        return ['netByRate' => $netByRate, 'vatByRate' => $vatByRate, 'total' => $total];
    }

    private function rateIndex(int $rate): string
    {
        return match ($rate) {
            23 => '1',
            8  => '2',
            5  => '3',
            0  => '4',
            default => '1',
        };
    }

    private function fmt(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function el(DOMDocument $doc, string $name, string $value = ''): DOMElement
    {
        $el = $doc->createElement($name, '');
        if ($value !== '') {
            $el->appendChild($doc->createTextNode($value));
        }

        return $el;
    }
}
