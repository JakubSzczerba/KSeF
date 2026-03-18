<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Application;

use Ksef\Backend\Authentication\Application\TokenRefreshingExecutor;
use Ksef\Backend\Invoice\Application\Contract\InvoiceEncryptor;
use Ksef\Backend\Invoice\Domain\InvoiceSubmission;
use Ksef\Backend\Invoice\Domain\ValueObject\FormCode;
use Ksef\Backend\Invoice\Domain\ValueObject\InvoiceReferenceNumber;
use Ksef\Backend\Invoice\Domain\ValueObject\SessionReferenceNumber;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use Ksef\Backend\Shared\Application\Exception\IntegrationResponseException;
use Ksef\Backend\Shared\Application\Exception\InvalidCommandException;
use Ksef\Backend\Shared\Application\KsefStatusPoller;
use Throwable;

final class SendInvoiceHandler
{
    private const INVOICE_MAX_ATTEMPTS = 60;
    private const SESSION_MAX_ATTEMPTS = 30;

    /**
     * @var list<int>
     */
    private const INVOICE_TERMINAL_CODES = [200, 405, 410, 415, 430, 435, 440, 450, 500, 550];

    /**
     * @var list<int>
     */
    private const SESSION_TERMINAL_CODES = [200, 415, 440, 445, 500];

    public function __construct(
        private readonly KsefApi $ksefApi,
        private readonly InvoiceEncryptor $invoiceEncryptor,
        private readonly TokenRefreshingExecutor $tokenRefreshingExecutor,
        private readonly KsefStatusPoller $statusPoller
    ) {}

    public function execute(SendInvoiceCommand $command): InvoiceSubmission
    {
        if (trim($command->invoiceXml) === '') {
            throw new InvalidCommandException('Pusty XML faktury.');
        }

        $accessToken = $this->tokenRefreshingExecutor->getValidToken();
        $formCode = new FormCode($command->formSystemCode, $command->formSchemaVersion, $command->formValue);

        $sessionEncryptionData = $this->invoiceEncryptor->createSessionEncryptionData();
        $openSessionResponse = $this->ksefApi->openOnlineSession($accessToken->value, [
            'formCode' => [
                'systemCode' => $formCode->systemCode,
                'schemaVersion' => $formCode->schemaVersion,
                'value' => $formCode->value,
            ],
            'encryption' => [
                'encryptedSymmetricKey' => $sessionEncryptionData->encryptedSymmetricKeyBase64,
                'initializationVector' => $sessionEncryptionData->initializationVectorBase64,
            ],
        ]);

        $sessionReferenceNumber = (string) ($openSessionResponse['referenceNumber'] ?? '');
        if ($sessionReferenceNumber === '') {
            throw new IntegrationResponseException('Brak numeru referencyjnego sesji po otwarciu /sessions/online.');
        }
        $sessionReference = new SessionReferenceNumber($sessionReferenceNumber);

        $encryptedInvoice = $this->invoiceEncryptor->encryptInvoice($command->invoiceXml, $sessionEncryptionData);
        $sendInvoiceResponse = $this->ksefApi->sendInvoice($accessToken->value, $sessionReference->value, [
            'invoiceHash' => $encryptedInvoice->invoiceHashBase64,
            'invoiceSize' => $encryptedInvoice->invoiceSize,
            'encryptedInvoiceHash' => $encryptedInvoice->encryptedInvoiceHashBase64,
            'encryptedInvoiceSize' => $encryptedInvoice->encryptedInvoiceSize,
            'encryptedInvoiceContent' => $encryptedInvoice->encryptedInvoiceContentBase64,
            'offlineMode' => $command->offlineMode,
        ]);

        $invoiceReferenceNumber = (string) ($sendInvoiceResponse['referenceNumber'] ?? '');
        if ($invoiceReferenceNumber === '') {
            throw new IntegrationResponseException('Brak numeru referencyjnego faktury po wysyłce.');
        }
        $invoiceReference = new InvoiceReferenceNumber($invoiceReferenceNumber);

        $closeError = null;
        try {
            $this->ksefApi->closeOnlineSession($accessToken->value, $sessionReference->value);
        } catch (Throwable $e) {
            $closeError = $e->getMessage();
        }

        $invoiceStatus = $this->waitForInvoiceStatus($accessToken->value, $sessionReference->value, $invoiceReference->value);
        $this->ensureInvoiceSucceeded($invoiceStatus, $sessionReference->value, $invoiceReference->value);
        $sessionStatus = $this->waitForSessionStatus($accessToken->value, $sessionReference->value);

        return new InvoiceSubmission(
            $sessionReference,
            $invoiceReference,
            $invoiceStatus,
            $sessionStatus,
            $closeError
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForInvoiceStatus(string $accessToken, string $sessionReferenceNumber, string $invoiceReferenceNumber): array
    {
        return $this->statusPoller->pollUntilTerminal(
            fn (): array => $this->ksefApi->getSessionInvoiceStatus($accessToken, $sessionReferenceNumber, $invoiceReferenceNumber),
            self::INVOICE_TERMINAL_CODES,
            self::INVOICE_MAX_ATTEMPTS,
            'Timeout podczas oczekiwania na końcowy status faktury.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForSessionStatus(string $accessToken, string $sessionReferenceNumber): array
    {
        return $this->statusPoller->pollUntilTerminal(
            fn (): array => $this->ksefApi->getSessionStatus($accessToken, $sessionReferenceNumber),
            self::SESSION_TERMINAL_CODES,
            self::SESSION_MAX_ATTEMPTS,
            'Timeout podczas oczekiwania na końcowy status sesji.'
        );
    }

    /**
     * @param array<string, mixed> $invoiceStatus
     */
    private function ensureInvoiceSucceeded(
        array $invoiceStatus,
        string $sessionReferenceNumber,
        string $invoiceReferenceNumber
    ): void {
        $statusCode = (int) ($invoiceStatus['status']['code'] ?? 0);
        if ($statusCode === 200) {
            return;
        }

        $statusDescription = (string) ($invoiceStatus['status']['description'] ?? 'Nieznany błąd statusu faktury.');
        $details = $invoiceStatus['status']['details'] ?? [];
        $detailsText = '';

        if (is_array($details) && $details !== []) {
            $flattenedDetails = array_filter(
                array_map(
                    static fn (mixed $detail): string => is_scalar($detail) ? trim((string) $detail) : '',
                    $details
                ),
                static fn (string $detail): bool => $detail !== ''
            );

            if ($flattenedDetails !== []) {
                $detailsText = ' Szczegóły: ' . implode(' | ', $flattenedDetails);
            }
        }

        throw new IntegrationResponseException(
            sprintf(
                'Wysyłka faktury do KSeF zakończona statusem %d (%s). SessionRef: %s, InvoiceRef: %s.%s',
                $statusCode,
                $statusDescription,
                $sessionReferenceNumber,
                $invoiceReferenceNumber,
                $detailsText
            )
        );
    }
}
