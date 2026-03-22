<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Parser\Application;

use DOMDocument;
use DOMXPath;
use Ksef\Backend\Parser\Domain\Exception\InvalidStructuredInvoiceException;
use Ksef\Backend\Parser\Domain\Fa3StructuredInvoice;

final class Fa3StructuredInvoiceParser
{
    private const FA3_NAMESPACE = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    public function parse(string $xml): Fa3StructuredInvoice
    {
        if (trim($xml) === '') {
            throw new InvalidStructuredInvoiceException('Plik XML faktury jest pusty.');
        }

        if (str_contains($xml, 'your_nip')) {
            throw new InvalidStructuredInvoiceException(
                'W XML wykryto placeholder "your_nip". Podmień NIP sprzedawcy na rzeczywisty numer przed wysyłką.'
            );
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$doc->loadXML($xml, LIBXML_NONET)) {
                $message = 'Niepoprawny XML faktury.';
                $error = libxml_get_last_error();
                if ($error !== false && $error->message !== '') {
                    $message .= ' ' . trim($error->message);
                }

                throw new InvalidStructuredInvoiceException($message);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $doc->documentElement;
        if ($root === null) {
            throw new InvalidStructuredInvoiceException('Brak elementu głównego w XML faktury.');
        }

        $namespaceUri = $root->namespaceURI;
        if ($namespaceUri !== self::FA3_NAMESPACE) {
            throw new InvalidStructuredInvoiceException(
                sprintf(
                    'Nieprawidłowy namespace dla FA (3). Oczekiwano "%s", otrzymano "%s".',
                    self::FA3_NAMESPACE,
                    (string) $namespaceUri
                )
            );
        }

        $xpath = new DOMXPath($doc);
        $queryResult = $xpath->query('//*[local-name()="KodFormularza"]');
        $formNode = ($queryResult !== false) ? $queryResult->item(0) : null;
        if (!$formNode instanceof \DOMElement) {
            throw new InvalidStructuredInvoiceException('Brak elementu KodFormularza w XML faktury.');
        }

        $formSystemCode = trim((string) $formNode->attributes->getNamedItem('kodSystemowy')?->nodeValue);
        $formSchemaVersion = trim((string) $formNode->attributes->getNamedItem('wersjaSchemy')?->nodeValue);
        $formValue = trim((string) $formNode->textContent);

        if ($formSystemCode === '' || $formSchemaVersion === '' || $formValue === '') {
            throw new InvalidStructuredInvoiceException(
                'Nie można odczytać pełnego FormCode z XML (kodSystemowy, wersjaSchemy, wartość).'
            );
        }

        if ($formSystemCode !== 'FA (3)' || $formValue !== 'FA') {
            throw new InvalidStructuredInvoiceException(
                sprintf(
                    'Obsługiwany jest wyłącznie format FA (3). Otrzymano: kodSystemowy="%s", value="%s".',
                    $formSystemCode,
                    $formValue
                )
            );
        }

        return new Fa3StructuredInvoice(
            $xml,
            $root->localName ?: $root->nodeName,
            $namespaceUri,
            $formSystemCode,
            $formSchemaVersion,
            $formValue
        );
    }
}
