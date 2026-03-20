<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Infrastructure\Signing;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Ksef\Backend\Shared\Infrastructure\Exception\XmlSignatureException;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

final class XadesSignatureSupport
{
    /**
     * @param array<string, mixed> $issuerData
     */
    public function buildIssuerName(array $issuerData): string
    {
        $parts = [];
        foreach ($issuerData as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $nestedValue) {
                    if (is_scalar($nestedValue)) {
                        array_unshift($parts, sprintf('%s=%s', $name, (string) $nestedValue));
                    }
                }
                continue;
            }

            if (is_scalar($value)) {
                array_unshift($parts, sprintf('%s=%s', $name, (string) $value));
            }
        }

        return implode(',', $parts);
    }

    public function convertEcdsaDerSignatureToXmlDsig(string $derSignature, int $keySizeBytes): string
    {
        $offset = 0;
        if (ord($derSignature[$offset++] ?? "\x00") !== 0x30) {
            throw new XmlSignatureException('Niepoprawny format podpisu ECDSA DER (brak SEQUENCE).');
        }

        $this->readAsn1Length($derSignature, $offset);
        $r = $this->readAsn1Integer($derSignature, $offset);
        $s = $this->readAsn1Integer($derSignature, $offset);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        if (strlen($r) > $keySizeBytes || strlen($s) > $keySizeBytes) {
            throw new XmlSignatureException('Niepoprawna długość składowych podpisu ECDSA.');
        }

        return str_pad($r, $keySizeBytes, "\x00", STR_PAD_LEFT) . str_pad($s, $keySizeBytes, "\x00", STR_PAD_LEFT);
    }

    public function computeEnvelopedDocumentDigestSha256(DOMDocument $doc): string
    {
        $docClone = new DOMDocument();
        $docClone->loadXML($doc->saveXML() ?: '');

        $xpath = new DOMXPath($docClone);
        $xpath->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
        $signatures = $xpath->query('//ds:Signature');
        if ($signatures !== false) {
            foreach (iterator_to_array($signatures) as $signatureNode) {
                if ($signatureNode instanceof DOMElement && $signatureNode->parentNode) {
                    $signatureNode->parentNode->removeChild($signatureNode);
                }
            }
        }

        $canonical = $docClone->C14N(false, false);
        if ($canonical === false) {
            throw new XmlSignatureException('Nie udało się wykonać canonicalization dokumentu do digestu enveloped.');
        }

        return base64_encode(hash('sha256', $canonical, true));
    }

    private function readAsn1Integer(string $input, int &$offset): string
    {
        if (ord($input[$offset++] ?? "\x00") !== 0x02) {
            throw new XmlSignatureException('Niepoprawny format podpisu ECDSA DER (brak INTEGER).');
        }

        $length = $this->readAsn1Length($input, $offset);
        $value = substr($input, $offset, $length);
        if (strlen($value) !== $length) {
            throw new XmlSignatureException('Niepoprawny format podpisu ECDSA DER (ucięta wartość INTEGER).');
        }
        $offset += $length;

        return $value;
    }

    private function readAsn1Length(string $input, int &$offset): int
    {
        $first = ord($input[$offset++] ?? "\x00");
        if (($first & 0x80) === 0) {
            return $first;
        }

        $numBytes = $first & 0x7F;
        if ($numBytes < 1 || $numBytes > 4) {
            throw new XmlSignatureException('Niepoprawna długość ASN.1 w podpisie DER.');
        }

        $length = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $length = ($length << 8) | ord($input[$offset++] ?? "\x00");
        }

        return $length;
    }
}
