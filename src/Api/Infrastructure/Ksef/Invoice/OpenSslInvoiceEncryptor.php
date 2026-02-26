<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Api\Infrastructure\Ksef\Invoice;

use Ksef\Api\Application\Contract\InvoiceEncryptor;
use Ksef\Api\Application\Contract\KsefApi;
use Ksef\Api\Domain\Invoice\EncryptedInvoice;
use Ksef\Api\Domain\Invoice\SessionEncryptionData;
use Ksef\Api\Infrastructure\Exception\CryptographyException;

final class OpenSslInvoiceEncryptor implements InvoiceEncryptor
{
    public function __construct(private readonly KsefApi $ksefApi) {}

    public function createSessionEncryptionData(): SessionEncryptionData
    {
        $publicKeyCertificatePem = $this->getSymmetricEncryptionCertificatePem();
        $symmetricKey = random_bytes(32);
        $initializationVector = random_bytes(16);

        $encryptedSymmetricKey = $this->encryptSymmetricKeyWithOaepSha256($symmetricKey, $publicKeyCertificatePem);

        return new SessionEncryptionData(
            base64_encode($encryptedSymmetricKey),
            base64_encode($initializationVector),
            $symmetricKey,
            $initializationVector
        );
    }

    public function encryptInvoice(string $invoiceXml, SessionEncryptionData $sessionEncryptionData): EncryptedInvoice
    {
        $invoiceHash = base64_encode(hash('sha256', $invoiceXml, true));
        $invoiceSize = strlen($invoiceXml);

        $encryptedInvoiceRaw = openssl_encrypt(
            $invoiceXml,
            'aes-256-cbc',
            $sessionEncryptionData->symmetricKeyRaw,
            OPENSSL_RAW_DATA,
            $sessionEncryptionData->initializationVectorRaw
        );

        if (!is_string($encryptedInvoiceRaw)) {
            throw new CryptographyException('Nie udało się zaszyfrować faktury AES-256-CBC.');
        }

        return new EncryptedInvoice(
            $invoiceHash,
            $invoiceSize,
            base64_encode(hash('sha256', $encryptedInvoiceRaw, true)),
            strlen($encryptedInvoiceRaw),
            base64_encode($encryptedInvoiceRaw)
        );
    }

    private function getSymmetricEncryptionCertificatePem(): string
    {
        $certificates = $this->ksefApi->getPublicKeyCertificates();
        $candidate = null;
        $now = time();

        foreach ($certificates as $certificateInfo) {
            $usage = $certificateInfo['usage'] ?? [];
            if (!is_array($usage) || !in_array('SymmetricKeyEncryption', $usage, true)) {
                continue;
            }

            $validFrom = isset($certificateInfo['validFrom']) ? strtotime((string) $certificateInfo['validFrom']) : false;
            $validTo = isset($certificateInfo['validTo']) ? strtotime((string) $certificateInfo['validTo']) : false;
            if ($validFrom === false || $validTo === false || $validFrom > $now || $validTo < $now) {
                continue;
            }

            if ($candidate === null) {
                $candidate = $certificateInfo;
                continue;
            }

            $candidateValidTo = strtotime((string) ($candidate['validTo'] ?? '')) ?: 0;
            if ($validTo > $candidateValidTo) {
                $candidate = $certificateInfo;
            }
        }

        if (!is_array($candidate) || empty($candidate['certificate'])) {
            throw new CryptographyException('Nie znaleziono aktywnego certyfikatu MF do SymmetricKeyEncryption.');
        }

        $certificateBody = chunk_split((string) $candidate['certificate'], 64, "\n");

        return "-----BEGIN CERTIFICATE-----\n" . $certificateBody . "-----END CERTIFICATE-----\n";
    }

    private function encryptSymmetricKeyWithOaepSha256(string $symmetricKey, string $publicKeyCertificatePem): string
    {
        $tmpBase = rtrim(sys_get_temp_dir(), '/') . '/ksef-rsa-' . bin2hex(random_bytes(8));
        $certPath = $tmpBase . '-cert.pem';
        $pubPath = $tmpBase . '-pub.pem';
        $inPath = $tmpBase . '-in.bin';
        $outPath = $tmpBase . '-out.bin';

        try {
            if (file_put_contents($certPath, $publicKeyCertificatePem) === false) {
                throw new CryptographyException('Nie udało się zapisać certyfikatu tymczasowego.');
            }
            if (file_put_contents($inPath, $symmetricKey) === false) {
                throw new CryptographyException('Nie udało się zapisać danych wejściowych szyfrowania.');
            }

            $extractPubCmd = sprintf(
                'openssl x509 -pubkey -noout -in %s > %s 2>&1',
                escapeshellarg($certPath),
                escapeshellarg($pubPath)
            );
            exec($extractPubCmd, $extractPubOutput, $extractPubExitCode);
            if ($extractPubExitCode !== 0) {
                throw new CryptographyException('Nie udało się wyodrębnić klucza publicznego MF: ' . implode("\n", $extractPubOutput));
            }

            $encryptCmd = sprintf(
                'openssl pkeyutl -encrypt -pubin -inkey %s -in %s -out %s -pkeyopt rsa_padding_mode:oaep -pkeyopt rsa_oaep_md:sha256 -pkeyopt rsa_mgf1_md:sha256 2>&1',
                escapeshellarg($pubPath),
                escapeshellarg($inPath),
                escapeshellarg($outPath)
            );
            exec($encryptCmd, $encryptOutput, $encryptExitCode);
            if ($encryptExitCode !== 0) {
                throw new CryptographyException('Nie udało się zaszyfrować klucza symetrycznego OAEP SHA-256: ' . implode("\n", $encryptOutput));
            }

            $encryptedSymmetricKey = file_get_contents($outPath);
            if (!is_string($encryptedSymmetricKey) || $encryptedSymmetricKey === '') {
                throw new CryptographyException('Pusty wynik szyfrowania klucza symetrycznego.');
            }

            return $encryptedSymmetricKey;
        } finally {
            foreach ([$certPath, $pubPath, $inPath, $outPath] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }
}
