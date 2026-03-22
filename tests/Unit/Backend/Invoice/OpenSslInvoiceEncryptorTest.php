<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Backend\Invoice;

use Ksef\Backend\Invoice\Infrastructure\Encryption\OpenSslInvoiceEncryptor;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenSslInvoiceEncryptorTest extends TestCase
{
    #[Test]
    public function shouldEncryptAndDecryptInvoiceWithAes256Cbc(): void
    {
        $api = $this->createMock(KsefApi::class);
        $api->method('getPublicKeyCertificates')->willReturn([
            [
                'usage' => ['SymmetricKeyEncryption'],
                'validFrom' => date('Y-m-d', strtotime('-1 day')),
                'validTo' => date('Y-m-d', strtotime('+1 day')),
                'certificate' => $this->generateSelfSignedCertBase64(),
            ],
        ]);

        $encryptor = new OpenSslInvoiceEncryptor($api);
        $sessionData = $encryptor->createSessionEncryptionData();
        $invoiceXml = '<Faktura>Test</Faktura>';

        $encrypted = $encryptor->encryptInvoice($invoiceXml, $sessionData);

        $decryptedRaw = openssl_decrypt(
            base64_decode($encrypted->encryptedInvoiceContentBase64),
            'aes-256-cbc',
            $sessionData->symmetricKeyRaw,
            OPENSSL_RAW_DATA,
            $sessionData->initializationVectorRaw
        );

        self::assertSame($invoiceXml, $decryptedRaw);
        self::assertSame(strlen($invoiceXml), $encrypted->invoiceSize);
        self::assertNotEmpty($sessionData->encryptedSymmetricKeyBase64);
        self::assertNotEmpty($sessionData->initializationVectorBase64);
    }

    private function generateSelfSignedCertBase64(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key, 'Failed to generate RSA key pair.');

        $csrResult = openssl_csr_new(['CN' => 'KSeF-Test'], $key);
        self::assertInstanceOf(\OpenSSLCertificateSigningRequest::class, $csrResult, 'Failed to generate CSR.');

        $cert = openssl_csr_sign($csrResult, null, $key, 365);
        self::assertNotFalse($cert, 'Failed to sign certificate.');

        openssl_x509_export($cert, $certPem);

        // Extract only the base64 body (no header/footer/newlines)
        $body = preg_replace('/-----[^-]+-----|\s/', '', $certPem);

        return (string) $body;
    }
}
