<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Infrastructure\Encryption;

use Ksef\Backend\Invoice\Application\Contract\InvoiceEncryptor;
use Ksef\Backend\Invoice\Domain\EncryptedInvoice;
use Ksef\Backend\Invoice\Domain\SessionEncryptionData;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use Ksef\Backend\Shared\Infrastructure\Exception\CryptographyException;

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
        $pubKeyPem = $this->extractPublicKeyPem($publicKeyCertificatePem);

        // Write only the non-sensitive public key to a temp file.
        // The sensitive symmetric key is passed via stdin — never written to disk.
        $pubKeyPath = tempnam(sys_get_temp_dir(), 'ksef-pub-');
        if (false === $pubKeyPath) {
            throw new CryptographyException('Nie udało się utworzyć pliku tymczasowego dla klucza publicznego.');
        }

        try {
            file_put_contents($pubKeyPath, $pubKeyPem);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open(
                sprintf(
                    'openssl pkeyutl -encrypt -pubin -inkey %s -pkeyopt rsa_padding_mode:oaep -pkeyopt rsa_oaep_md:sha256 -pkeyopt rsa_mgf1_md:sha256',
                    escapeshellarg($pubKeyPath)
                ),
                $descriptors,
                $pipes
            );

            if (!is_resource($process)) {
                throw new CryptographyException('Nie udało się uruchomić openssl pkeyutl.');
            }

            fwrite($pipes[0], $symmetricKey);
            fclose($pipes[0]);

            $encryptedKey = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0 || !is_string($encryptedKey) || $encryptedKey === '') {
                throw new CryptographyException('Nie udało się zaszyfrować klucza symetrycznego OAEP SHA-256: ' . $stderr);
            }

            return $encryptedKey;
        } finally {
            @unlink($pubKeyPath);
        }
    }

    private function extractPublicKeyPem(string $publicKeyCertificatePem): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open('openssl x509 -pubkey -noout', $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new CryptographyException('Nie udało się uruchomić openssl x509.');
        }

        fwrite($pipes[0], $publicKeyCertificatePem);
        fclose($pipes[0]);

        $pubKey = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !is_string($pubKey) || $pubKey === '') {
            throw new CryptographyException('Nie udało się wyodrębnić klucza publicznego MF: ' . $stderr);
        }

        return $pubKey;
    }
}
