<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Infrastructure\Signing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Ksef\Backend\Authentication\Application\Contract\AuthChallengeSigner;
use Ksef\Backend\Shared\Infrastructure\Exception\XmlSignatureException;
use OpenSSLAsymmetricKey;
use Psr\Log\LoggerInterface;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

final class XadesAuthChallengeSigner implements AuthChallengeSigner
{
    private const AUTH_XML_NS = 'http://ksef.mf.gov.pl/auth/token/2.0';
    private const XADES_NS = 'http://uri.etsi.org/01903/v1.3.2#';

    public function __construct(
        private readonly string $ksefNip,
        private readonly string $certificatePath,
        private readonly string $certificateKeyPath,
        private readonly string $certificatePassword,
        private readonly XadesSignatureSupport $signatureSupport,
        private readonly LoggerInterface $logger
    ) {}

    public function signChallenge(string $challenge): string
    {
        $this->logger->debug('KSeF signing: starting XAdES challenge signing');

        try {
            $doc = $this->prepareAuthXmlDocument($challenge);
            $result = $this->signXmlDocument($doc);
        } catch (XmlSignatureException $e) {
            $this->logger->error('KSeF signing: XAdES signing failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $this->logger->debug('KSeF signing: XAdES challenge signing complete');

        return $result;
    }

    private function prepareAuthXmlDocument(string $challenge): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $root = $doc->createElementNS(self::AUTH_XML_NS, 'AuthTokenRequest');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $doc->appendChild($root);

        $root->appendChild($doc->createElementNS(self::AUTH_XML_NS, 'Challenge', $challenge));
        $contextIdentifier = $doc->createElementNS(self::AUTH_XML_NS, 'ContextIdentifier');
        $contextIdentifier->appendChild($doc->createElementNS(self::AUTH_XML_NS, 'Nip', $this->ksefNip));
        $root->appendChild($contextIdentifier);
        $root->appendChild($doc->createElementNS(self::AUTH_XML_NS, 'SubjectIdentifierType', 'certificateSubject'));

        return $doc;
    }

    private function signXmlDocument(DOMDocument $doc): string
    {
        $certContent = file_get_contents($this->certificatePath);
        if (!$certContent) {
            throw new XmlSignatureException('Nie można wczytać certyfikatu z: ' . $this->certificatePath);
        }

        $keyContent = file_get_contents($this->certificateKeyPath);
        if (!$keyContent) {
            throw new XmlSignatureException('Nie można wczytać klucza prywatnego z: ' . $this->certificateKeyPath);
        }

        $isEncrypted = strpos($keyContent, 'ENCRYPTED') !== false;
        $privateKey = openssl_pkey_get_private($keyContent, $this->certificatePassword ?: null);
        if (!$privateKey) {
            $error = openssl_error_string();
            throw new XmlSignatureException(
                'Nie można załadować klucza prywatnego: ' . $error . "\n" .
                'Klucz zaszyfrowany: ' . ($isEncrypted ? 'TAK' : 'NIE') . "\n" .
                'Podano hasło: ' . ($this->certificatePassword ? 'TAK' : 'NIE')
            );
        }

        $root = $doc->documentElement;
        if (!$root instanceof DOMElement) {
            throw new XmlSignatureException('Niepoprawny dokument XML - brak elementu głównego.');
        }

        $signatureId = 'Signature-' . strtoupper(bin2hex(random_bytes(8)));
        $signedPropertiesId = 'SignedProperties-' . strtoupper(bin2hex(random_bytes(8)));

        $certBase64 = XMLSecurityDSig::get509XCert($certContent);
        if ($certBase64 === '') {
            throw new XmlSignatureException('Nie udało się odczytać certyfikatu X509 z pliku PEM.');
        }

        $x509 = openssl_x509_read($certContent);
        $x509Data = $x509 ? openssl_x509_parse($x509, false) : false;
        if (!is_array($x509Data)) {
            throw new XmlSignatureException('Nie udało się sparsować certyfikatu X509.');
        }

        $certDer = base64_decode($certBase64, true);
        if ($certDer === false) {
            throw new XmlSignatureException('Nie udało się zdekodować certyfikatu X509 (Base64).');
        }

        $certDigest = base64_encode(hash('sha256', $certDer, true));
        $issuerName = $this->signatureSupport->buildIssuerName($x509Data['issuer'] ?? []);
        $serialNumber = (string) ($x509Data['serialNumber'] ?? '');
        if ($serialNumber === '') {
            $serialNumber = (string) ($x509Data['serialNumberHex'] ?? '');
        }

        $objDSig = new XMLSecurityDSig();
        $sigNode = $objDSig->sigNode;
        if (!$sigNode instanceof DOMElement) {
            throw new XmlSignatureException('XMLSecurityDSig nie zainicjalizował węzła podpisu.');
        }
        $sigNode->setAttribute('Id', $signatureId);
        $insertedSignature = $objDSig->appendSignature($root);

        $objDSig = new XMLSecurityDSig();
        // @phpstan-ignore assign.propertyType
        $objDSig->sigNode = $insertedSignature;
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);

        $qualifyingProperties = $doc->createElementNS(self::XADES_NS, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', '#' . $signatureId);

        $signedProperties = $doc->createElementNS(self::XADES_NS, 'xades:SignedProperties');
        $signedProperties->setAttribute('Id', $signedPropertiesId);
        $qualifyingProperties->appendChild($signedProperties);

        $signedSignatureProperties = $doc->createElementNS(self::XADES_NS, 'xades:SignedSignatureProperties');
        $signedProperties->appendChild($signedSignatureProperties);
        $signedSignatureProperties->appendChild(
            $doc->createElementNS(self::XADES_NS, 'xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z'))
        );

        $signingCertificate = $doc->createElementNS(self::XADES_NS, 'xades:SigningCertificate');
        $signedSignatureProperties->appendChild($signingCertificate);

        $certNode = $doc->createElementNS(self::XADES_NS, 'xades:Cert');
        $signingCertificate->appendChild($certNode);

        $certDigestNode = $doc->createElementNS(self::XADES_NS, 'xades:CertDigest');
        $certNode->appendChild($certDigestNode);
        $digestMethodNode = $doc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:DigestMethod');
        $digestMethodNode->setAttribute('Algorithm', XMLSecurityDSig::SHA256);
        $certDigestNode->appendChild($digestMethodNode);
        $certDigestNode->appendChild($doc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:DigestValue', $certDigest));

        $issuerSerialNode = $doc->createElementNS(self::XADES_NS, 'xades:IssuerSerial');
        $certNode->appendChild($issuerSerialNode);
        $issuerSerialNode->appendChild($doc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:X509IssuerName', $issuerName));
        $issuerSerialNode->appendChild($doc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:X509SerialNumber', $serialNumber));

        $objDSig->addObject($qualifyingProperties);
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );

        $currentSigNode = $objDSig->sigNode;
        if (!$currentSigNode instanceof DOMElement) {
            throw new XmlSignatureException('Brak węzła podpisu po addReference.');
        }
        $ownerDoc = $currentSigNode->ownerDocument;
        if (!$ownerDoc instanceof DOMDocument) {
            throw new XmlSignatureException('Brak dokumentu właściciela węzła podpisu.');
        }

        $sigDocXPath = new DOMXPath($ownerDoc);
        $sigDocXPath->registerNamespace('xades', self::XADES_NS);

        $signedPropertiesQueryResult = $sigDocXPath->query(
            sprintf('//xades:SignedProperties[@Id="%s"]', $signedPropertiesId),
            $currentSigNode
        );
        $signedPropertiesInSignature = ($signedPropertiesQueryResult !== false) ? $signedPropertiesQueryResult->item(0) : null;
        if (!$signedPropertiesInSignature instanceof DOMElement) {
            throw new XmlSignatureException('Nie znaleziono xades:SignedProperties w wygenerowanym ds:Object.');
        }

        $objDSig->addReference(
            $signedPropertiesInSignature, // @phpstan-ignore argument.type
            XMLSecurityDSig::SHA256,
            [XMLSecurityDSig::C14N],
            ['overwrite' => false, 'id_name' => 'Id']
        );

        $sigDocXPath->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);

        $signedPropsRefResult = $sigDocXPath->query(sprintf('//ds:Reference[@URI="#%s"]', $signedPropertiesId));
        $signedPropsRef = ($signedPropsRefResult !== false) ? $signedPropsRefResult->item(0) : null;
        if ($signedPropsRef instanceof DOMElement) {
            $signedPropsRef->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
            foreach (iterator_to_array($signedPropsRef->childNodes) as $childNode) {
                if ($childNode instanceof DOMElement && $childNode->localName === 'Transforms') {
                    $signedPropsRef->removeChild($childNode);
                    break;
                }
            }
        }

        $documentRefResult = $sigDocXPath->query('//ds:Reference[@URI=""]');
        $documentRef = ($documentRefResult !== false) ? $documentRefResult->item(0) : null;
        if ($documentRef instanceof DOMElement) {
            $docDigestValue = (string) $sigDocXPath->evaluate('string(./ds:DigestValue)', $documentRef);
            $computedDocDigestValue = $this->signatureSupport->computeEnvelopedDocumentDigestSha256($doc);
            if ($docDigestValue !== $computedDocDigestValue) {
                $digestNodeResult = $sigDocXPath->query('./ds:DigestValue', $documentRef);
                $digestNode = ($digestNodeResult !== false) ? $digestNodeResult->item(0) : null;
                if ($digestNode instanceof DOMElement) {
                    $digestNode->nodeValue = $computedDocDigestValue;
                }
            }
        }

        $this->signSignedInfo($currentSigNode, $privateKey);
        $objDSig->add509Cert($certContent, true, false, ['issuerSerial' => true]);

        $xmlResult = $doc->saveXML();
        if ($xmlResult === false) {
            throw new XmlSignatureException('Nie udało się serializować podpisanego dokumentu XML.');
        }

        return $xmlResult;
    }

    private function signSignedInfo(DOMElement $signatureNode, OpenSSLAsymmetricKey $privateKey): void
    {
        $details = openssl_pkey_get_details($privateKey);
        if (!is_array($details) || !isset($details['type'])) {
            throw new XmlSignatureException('Nie udało się odczytać typu klucza prywatnego.');
        }

        $signatureMethodAlgorithm = match ((int) $details['type']) {
            OPENSSL_KEYTYPE_EC => 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256',
            OPENSSL_KEYTYPE_RSA => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
            default => throw new XmlSignatureException('Nieobsługiwany typ klucza dla podpisu XMLDSig/XAdES.'),
        };

        $ownerDoc = $signatureNode->ownerDocument;
        if (!$ownerDoc instanceof DOMDocument) {
            throw new XmlSignatureException('Brak dokumentu właściciela węzła podpisu.');
        }

        $xpath = new DOMXPath($ownerDoc);
        $xpath->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);

        $signedInfoResult = $xpath->query('./ds:SignedInfo', $signatureNode);
        $signedInfo = ($signedInfoResult !== false) ? $signedInfoResult->item(0) : null;
        if (!$signedInfo instanceof DOMElement) {
            throw new XmlSignatureException('Brak ds:SignedInfo w podpisie.');
        }

        $signatureMethodResult = $xpath->query('./ds:SignatureMethod', $signedInfo);
        $signatureMethod = ($signatureMethodResult !== false) ? $signatureMethodResult->item(0) : null;
        if (!$signatureMethod instanceof DOMElement) {
            throw new XmlSignatureException('Brak ds:SignatureMethod w SignedInfo.');
        }
        $signatureMethod->setAttribute('Algorithm', $signatureMethodAlgorithm);

        $canonicalSignedInfo = $signedInfo->C14N(false, false);
        if ($canonicalSignedInfo === false) {
            throw new XmlSignatureException('Nie udało się skanonikalizować ds:SignedInfo.');
        }
        if (!openssl_sign($canonicalSignedInfo, $rawSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new XmlSignatureException('Nie udało się podpisać ds:SignedInfo.');
        }

        if ((int) $details['type'] === OPENSSL_KEYTYPE_EC) {
            $keySizeBytes = (int) ceil(((int) ($details['bits'] ?? 256)) / 8);
            $rawSignature = $this->signatureSupport->convertEcdsaDerSignatureToXmlDsig($rawSignature, $keySizeBytes);
        }

        $signatureValueResult = $xpath->query('./ds:SignatureValue', $signatureNode);
        $signatureValue = ($signatureValueResult !== false) ? $signatureValueResult->item(0) : null;
        if (!$signatureValue instanceof DOMElement) {
            $signatureValue = $ownerDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:SignatureValue');
            $firstAfterSignedInfo = $signedInfo->nextSibling;
            if ($firstAfterSignedInfo instanceof DOMNode) {
                $signatureNode->insertBefore($signatureValue, $firstAfterSignedInfo);
            } else {
                $signatureNode->appendChild($signatureValue);
            }
        }

        $signatureValue->nodeValue = base64_encode($rawSignature);
    }
}
