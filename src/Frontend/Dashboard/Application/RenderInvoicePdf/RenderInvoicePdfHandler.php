<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Application\RenderInvoicePdf;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Dompdf\Dompdf;
use Dompdf\Options;
use Ksef\Frontend\Shared\Exception\FrontendRequestException;
use Throwable;

final class RenderInvoicePdfHandler
{
    private const BRAND_NAME = 'Exasphere';
    private const LOGO_PATH = '/docs/exampleLogo.svg';

    public function render(string $xml, string $ksefNumber): string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                throw new FrontendRequestException('Nie można wygenerować PDF: niepoprawny XML faktury.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);

        $summary = $this->buildSummary($xpath, $xml, $ksefNumber);
        $rowsHtml = $this->buildRowsHtml($xpath);
        $additionalInfoRowsHtml = $this->buildAdditionalInfoRowsHtml($xpath);
        $logoDataUri = $this->loadLogoDataUri();

        $html = sprintf(
            '<html><head><meta charset="utf-8"><style>
                @page { margin: 18mm 14mm 22mm; }
                body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; line-height: 1.32; }
                .masthead { width: 100%%; margin: 0 0 9mm; border-bottom: 2px solid #dbe9f6; padding-bottom: 7mm; table-layout: fixed; }
                .masthead td { vertical-align: bottom; }
                .brand { width: 44%%; }
                .brand-wrap { position: relative; height: 106px; }
                .logo-wrap {
                    display: inline-block;
                    position: absolute;
                    top: 0;
                    left: 0;
                    background: #ffffff;
                    border: 1px solid #dbe9f6;
                    border-radius: 8px;
                    padding: 4mm 5mm 3mm;
                }
                .logo { width: 145px; height: auto; }
                .brand-subtitle { position: absolute; bottom: 0; margin: 0; font-size: 10px; color: #3d5d79; }
                .doc-head { width: 56%%; text-align: right; }
                .head-qr { width: 100%%; border-collapse: separate; border-spacing: 6px 0; }
                .head-qr td {
                    width: 50%%;
                    border: 1px solid #dbe9f6;
                    border-radius: 8px;
                    background: #f7fbff;
                    padding: 5px;
                    text-align: center;
                    vertical-align: top;
                }
                .head-qr .qr-title { margin: 0 0 4px; font-size: 8px; }
                .head-qr img { width: 78px; height: 78px; }
                .meta {
                    width: 100%%; border-collapse: separate; border-spacing: 8px 0; margin: 0 0 9px;
                }
                .meta td {
                    border: 1px solid #dbe9f6; border-radius: 10px; background: #f7fbff; vertical-align: top; padding: 9px 10px;
                }
                .payment-meta { width: 100%%; border-collapse: separate; border-spacing: 8px 0; margin: 0 0 10px; }
                .payment-meta td {
                    border: 1px solid #dbe9f6; border-radius: 10px; background: #f7fbff; vertical-align: top; padding: 9px 10px;
                }
                .payment-line { margin: 0; font-size: 10px; color: #1e293b; }
                .payment-item { display: inline-block; min-width: 24%%; margin-right: 8px; vertical-align: top; }
                .payment-item strong { display: block; font-size: 9px; color: #4e6882; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px; }
                .label { display: block; font-size: 9px; color: #4e6882; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px; }
                .value { font-size: 11px; font-weight: 700; color: #0f172a; }
                .parties { width: 100%%; border-collapse: collapse; margin: 0 0 10px; }
                .parties td { width: 50%%; border: 1px solid #dbe9f6; padding: 10px; vertical-align: top; }
                .parties h3 { margin: 0 0 6px; font-size: 11px; color: #0b2942; }
                .parties p { margin: 1px 0; color: #1e293b; font-size: 10px; }
                .lines { width: 100%%; border-collapse: collapse; margin: 0 0 10px; }
                .lines th, .lines td { border: 1px solid #dbe9f6; padding: 7px 6px; font-size: 9px; }
                .lines th { background: #edf5fc; color: #0b2942; text-transform: uppercase; letter-spacing: 0.03em; }
                .lines td.num { text-align: right; }
                .summary { width: 42%%; margin-left: auto; border-collapse: collapse; }
                .summary td { border: 1px solid #dbe9f6; padding: 7px; font-size: 10px; }
                .summary td:last-child { text-align: right; font-weight: 700; }
                .summary tr.total td { background: #eef7ff; font-size: 11px; color: #0b2942; }
                .notes { width: 100%%; border-collapse: collapse; margin-top: 9px; }
                .notes th, .notes td { border: 1px solid #dbe9f6; padding: 7px 6px; font-size: 9px; }
                .notes th { background: #edf5fc; color: #0b2942; text-transform: uppercase; letter-spacing: 0.03em; }
                .notes td.key { width: 1%%; white-space: nowrap; font-weight: 700; color: #0b2942; }
                .notes td.value { width: auto; font-weight: 400; }
                .qr-grid { width: 100%%; margin-top: 12px; border-collapse: collapse; }
                .qr-grid td { width: 50%%; border: 1px solid #dbe9f6; padding: 8px; vertical-align: top; }
                .qr-title { margin: 0 0 6px; font-size: 9px; color: #0b2942; text-transform: uppercase; }
                .qr-box { display: inline-block; width: 105px; height: 105px; }
                .qr-note { margin: 6px 0 0; font-size: 8px; color: #475569; }
                .footer {
                    position: fixed;
                    bottom: -12mm;
                    left: 0;
                    right: 0;
                    font-size: 8px;
                    color: #64748b;
                    text-align: center;
                }
            </style></head><body>
                <table class="masthead">
                    <tr>
                        <td class="brand">
                            <div class="brand-wrap">
                                <div class="logo-wrap">%s</div>
                                <p class="brand-subtitle">System fakturowania i integracji KSeF</p>
                            </div>
                        </td>
                        <td class="doc-head">
                            <table class="head-qr">
                                <tr>
                                    <td>
                                        <p class="qr-title">QR identyfikacyjny</p>
                                        <img src="%s" alt="QR identyfikacyjny" width="78" height="78" />
                                    </td>
                                    <td>
                                        <p class="qr-title">QR kontrolny</p>
                                        <img src="%s" alt="QR kontrolny" width="78" height="78" />
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table class="meta">
                    <tr>
                        <td><span class="label">Numer KSeF</span><span class="value">%s</span></td>
                        <td><span class="label">Numer faktury</span><span class="value">%s</span></td>
                        <td><span class="label">Data wystawienia</span><span class="value">%s</span></td>
                        <td><span class="label">Data sprzedaży</span><span class="value">%s</span></td>
                    </tr>
                </table>

                <table class="payment-meta">
                    <tr>
                        <td>
                            <p class="payment-line">
                                <span class="payment-item"><strong>Termin płatności</strong>%s</span>
                                <span class="payment-item"><strong>Forma płatności</strong>%s</span>
                                <span class="payment-item"><strong>Rachunek bankowy</strong>%s</span>
                                <span class="payment-item"><strong>Nazwa banku</strong>%s</span>
                            </p>
                        </td>
                    </tr>
                </table>

                <table class="parties">
                    <tr>
                        <td>
                            <h3>Sprzedawca</h3>
                            <p><strong>%s</strong></p>
                            <p>NIP: %s</p>
                            <p>%s</p>
                        </td>
                        <td>
                            <h3>Nabywca</h3>
                            <p><strong>%s</strong></p>
                            <p>NIP: %s</p>
                            <p>%s</p>
                        </td>
                    </tr>
                </table>

                <table class="lines">
                    <thead>
                        <tr>
                            <th>Lp.</th>
                            <th>Pozycja</th>
                            <th>Ilość</th>
                            <th>JM</th>
                            <th>Cena jedn.</th>
                            <th>Netto</th>
                            <th>VAT %%</th>
                        </tr>
                    </thead>
                    <tbody>%s</tbody>
                </table>

                <table class="summary">
                    <tr><td>Waluta</td><td>%s</td></tr>
                    <tr><td>Razem netto</td><td>%s</td></tr>
                    <tr><td>VAT</td><td>%s</td></tr>
                    <tr class="total"><td>Razem brutto</td><td>%s</td></tr>
                </table>

                <table class="notes">
                    <thead>
                        <tr>
                            <th colspan="2">Dodatkowe informacje</th>
                        </tr>
                    </thead>
                    <tbody>%s</tbody>
                </table>

                <div class="footer">Wygenerowano przez %s • %s</div>
            </body></html>',
            $logoDataUri !== ''
                ? sprintf('<a href="https://exasphere.io"><img class="logo" src="%s" alt="Logo %s" /></a>', $this->escape($logoDataUri), $this->escape(self::BRAND_NAME))
                : sprintf('<a href="https://exasphere.io">%s</a>', $this->escape(self::BRAND_NAME)),
            $this->escape($summary['identityQr']),
            $this->escape($summary['integrityQr']),
            $this->escape($summary['ksefNumber']),
            $this->escape($summary['invoiceNumber']),
            $this->escape($summary['issueDate']),
            $this->escape($summary['saleDate']),
            $this->escape($summary['paymentDueDate']),
            $this->escape($summary['paymentMethod']),
            $this->escape($summary['paymentAccount']),
            $this->escape($summary['paymentBankName']),
            $this->escape($summary['sellerName']),
            $this->escape($summary['sellerNip']),
            $this->escape($summary['sellerAddress']),
            $this->escape($summary['buyerName']),
            $this->escape($summary['buyerNip']),
            $this->escape($summary['buyerAddress']),
            $rowsHtml,
            $this->escape($summary['currency']),
            $this->escape($summary['totalNet']),
            $this->escape($summary['totalVat']),
            $this->escape($summary['totalGross']),
            $additionalInfoRowsHtml,
            $this->escape(self::BRAND_NAME),
            $this->escape($summary['generatedAt'])
        );

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultPaperSize', 'a4');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    private function loadLogoDataUri(): string
    {
        $logoPath = dirname(__DIR__, 5) . self::LOGO_PATH;
        if (!is_readable($logoPath)) {
            return '';
        }

        $logo = file_get_contents($logoPath);
        if (!is_string($logo) || trim($logo) === '') {
            return '';
        }

        return 'data:image/svg+xml;base64,' . base64_encode($logo);
    }

    /**
     * @return array{
     *   ksefNumber:string,
     *   invoiceNumber:string,
     *   issueDate:string,
     *   saleDate:string,
     *   currency:string,
     *   totalNet:string,
     *   totalVat:string,
     *   totalGross:string,
     *   sellerName:string,
     *   sellerNip:string,
     *   sellerAddress:string,
     *   buyerName:string,
     *   buyerNip:string,
     *   buyerAddress:string,
     *   paymentDueDate:string,
     *   paymentMethod:string,
     *   paymentAccount:string,
     *   paymentBankName:string,
     *   identityQr:string,
     *   integrityQr:string,
     *   generatedAt:string
     * }
     */
    private function buildSummary(DOMXPath $xpath, string $xml, string $ksefNumber): array
    {
        $invoiceNumber = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="P_2"][1]');
        $issueDate = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="P_1"][1]');
        $saleDate = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="P_6"][1]');
        $currency = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="KodWaluty"][1]');

        $totalNet = $this->firstAvailableText($xpath, [
            '//*[local-name()="Fa"]/*[local-name()="P_13_1"][1]',
            '//*[local-name()="Fa"]/*[local-name()="P_14_1"][1]',
        ]);
        $totalGross = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="P_15"][1]');
        $totalVat = $this->formatAmount((float) $totalGross - (float) $totalNet);

        $sellerName = $this->text($xpath, '//*[local-name()="Podmiot1"]/*[local-name()="DaneIdentyfikacyjne"]/*[local-name()="Nazwa"][1]');
        $sellerNip = $this->text($xpath, '//*[local-name()="Podmiot1"]/*[local-name()="DaneIdentyfikacyjne"]/*[local-name()="NIP"][1]');
        $sellerAddress = $this->address($xpath, '//*[local-name()="Podmiot1"]/*[local-name()="Adres"][1]');

        $buyerName = $this->text($xpath, '//*[local-name()="Podmiot2"]/*[local-name()="DaneIdentyfikacyjne"]/*[local-name()="Nazwa"][1]');
        $buyerNip = $this->text($xpath, '//*[local-name()="Podmiot2"]/*[local-name()="DaneIdentyfikacyjne"]/*[local-name()="NIP"][1]');
        $buyerAddress = $this->address($xpath, '//*[local-name()="Podmiot2"]/*[local-name()="Adres"][1]');
        $paymentDueDate = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="Platnosc"]/*[local-name()="TerminPlatnosci"]/*[local-name()="Termin"][1]');
        $paymentMethod = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="Platnosc"]/*[local-name()="FormaPlatnosci"][1]');
        $paymentAccount = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="Platnosc"]/*[local-name()="RachunekBankowy"]/*[local-name()="NrRB"][1]');
        $paymentBankName = $this->text($xpath, '//*[local-name()="Fa"]/*[local-name()="Platnosc"]/*[local-name()="RachunekBankowy"]/*[local-name()="NazwaBanku"][1]');

        $identityPayload = $this->buildKsefVerificationUrl($sellerNip, $issueDate, $xml);

        $integrityPayload = implode('|', [
            'KSEF-XML-SHA256',
            hash('sha256', $xml),
            $ksefNumber,
        ]);

        return [
            'ksefNumber' => $ksefNumber,
            'invoiceNumber' => $invoiceNumber,
            'issueDate' => $issueDate,
            'saleDate' => $saleDate,
            'currency' => $currency,
            'totalNet' => $totalNet,
            'totalVat' => $totalVat,
            'totalGross' => $totalGross,
            'sellerName' => $sellerName,
            'sellerNip' => $sellerNip,
            'sellerAddress' => $sellerAddress,
            'buyerName' => $buyerName,
            'buyerNip' => $buyerNip,
            'buyerAddress' => $buyerAddress,
            'paymentDueDate' => $paymentDueDate,
            'paymentMethod' => $paymentMethod,
            'paymentAccount' => $paymentAccount,
            'paymentBankName' => $paymentBankName,
            'identityQr' => $this->generateQrDataUri($identityPayload),
            'integrityQr' => $this->generateQrDataUri($integrityPayload),
            'generatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    private function buildKsefVerificationUrl(string $sellerNip, string $issueDate, string $xml): string
    {
        $normalizedNip = preg_replace('/\D+/', '', $sellerNip);
        if (!is_string($normalizedNip) || $normalizedNip === '') {
            return 'n/d';
        }

        $issueDateForUrl = $this->normalizeIssueDateForQr($issueDate);
        $hashBase64 = base64_encode(hash('sha256', $xml, true));
        $hashBase64Url = rtrim(strtr($hashBase64, '+/', '-_'), '=');

        return sprintf(
            'https://qr-test.ksef.mf.gov.pl/invoice/%s/%s/%s',
            $normalizedNip,
            $issueDateForUrl,
            $hashBase64Url
        );
    }

    private function normalizeIssueDateForQr(string $issueDate): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $issueDate);
        if ($date instanceof \DateTimeImmutable) {
            return $date->format('d-m-Y');
        }

        return str_replace('.', '-', $issueDate);
    }

    private function buildAdditionalInfoRowsHtml(DOMXPath $xpath): string
    {
        $rowsHtml = '';

        $descriptionNodes = $xpath->query('//*[local-name()="Fa"]/*[local-name()="DodatkowyOpis"]');
        foreach ($descriptionNodes !== false ? $descriptionNodes : [] as $descriptionNode) {
            if (!$descriptionNode instanceof DOMElement) {
                continue;
            }

            $key = trim((string) $xpath->evaluate('string(*[local-name()="Klucz"][1])', $descriptionNode));
            $value = trim((string) $xpath->evaluate('string(*[local-name()="Wartosc"][1])', $descriptionNode));
            if ($key === '' && $value === '') {
                continue;
            }

            $rowsHtml .= sprintf(
                '<tr><td class="key">%s</td><td class="value">%s</td></tr>',
                $this->escape($key !== '' ? $key : 'n/d'),
                $this->escape($value !== '' ? $value : 'n/d')
            );
        }

        if ($rowsHtml === '') {
            return '<tr><td class="key">n/d</td><td class="value">n/d</td></tr>';
        }

        return $rowsHtml;
    }

    private function buildRowsHtml(DOMXPath $xpath): string
    {
        $rowsHtml = '';
        $rowIndex = 1;

        $lines = $xpath->query('//*[local-name()="Fa"]/*[local-name()="FaWiersz"]');
        foreach ($lines !== false ? $lines : [] as $line) {
            if (!$line instanceof DOMElement) {
                continue;
            }

            $description = $this->childText($xpath, $line, '*[local-name()="P_7"][1]');
            $quantity = $this->childText($xpath, $line, '*[local-name()="P_8B"][1]');
            $unit = $this->childText($xpath, $line, '*[local-name()="P_8A"][1]');
            $unitPrice = $this->childText($xpath, $line, '*[local-name()="P_9A"][1]');
            $net = $this->childText($xpath, $line, '*[local-name()="P_11"][1]');
            $vatRate = $this->childText($xpath, $line, '*[local-name()="P_12"][1]');

            $rowsHtml .= sprintf(
                '<tr><td class="num">%s</td><td>%s</td><td class="num">%s</td><td>%s</td><td class="num">%s</td><td class="num">%s</td><td class="num">%s</td></tr>',
                $this->escape((string) $rowIndex++),
                $this->escape($description),
                $this->escape($quantity),
                $this->escape($unit),
                $this->escape($unitPrice),
                $this->escape($net),
                $this->escape($vatRate)
            );
        }

        if ($rowsHtml === '') {
            return '<tr><td colspan="7">Brak pozycji.</td></tr>';
        }

        return $rowsHtml;
    }

    private function address(DOMXPath $xpath, string $addressExpression): string
    {
        $addressResult = $xpath->query($addressExpression);
        $addressNode = ($addressResult !== false) ? $addressResult->item(0) : null;
        if (!$addressNode instanceof DOMElement) {
            return 'n/d';
        }

        $parts = [];
        foreach (['KodKraju', 'Wojewodztwo', 'Powiat', 'Gmina', 'Ulica', 'NrDomu', 'NrLokalu', 'Miejscowosc', 'KodPocztowy'] as $tag) {
            $value = trim((string) $xpath->evaluate(sprintf('string(*[local-name()="%s"][1])', $tag), $addressNode));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $parts !== [] ? implode(', ', $parts) : 'n/d';
    }

    /**
     * @param list<string> $expressions
     */
    private function firstAvailableText(DOMXPath $xpath, array $expressions): string
    {
        foreach ($expressions as $expression) {
            $value = $this->text($xpath, $expression);
            if ($value !== 'n/d') {
                return $value;
            }
        }

        return 'n/d';
    }

    private function formatAmount(float $value): string
    {
        if (!is_finite($value)) {
            return 'n/d';
        }

        return number_format($value, 2, '.', '');
    }

    private function generateQrDataUri(string $payload): string
    {
        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'outputBase64' => true,
                'eccLevel' => QRCode::ECC_M,
                'scale' => 4,
                'drawLightModules' => false,
            ]);

            return (new QRCode($options))->render($payload);
        } catch (Throwable) {
            return '';
        }
    }

    private function text(DOMXPath $xpath, string $expression): string
    {
        $result = $xpath->query($expression);
        $node = ($result !== false) ? $result->item(0) : null;
        if (!$node instanceof DOMElement) {
            return 'n/d';
        }

        $value = trim($node->textContent);

        return $value !== '' ? $value : 'n/d';
    }

    private function childText(DOMXPath $xpath, DOMElement $context, string $expression): string
    {
        $result = $xpath->query($expression, $context);
        $node = ($result !== false) ? $result->item(0) : null;
        if (!$node instanceof DOMElement) {
            return 'n/d';
        }

        $value = trim($node->textContent);

        return $value !== '' ? $value : 'n/d';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
