<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Application\GetInvoiceOverview;

use Ksef\Backend\Authentication\Application\AccessTokenStore;
use Ksef\Backend\Authentication\Application\Contract\AuthenticateHandlerInterface;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;
use Ksef\Frontend\Dashboard\Infrastructure\SubmittedInvoiceRepository;
use Throwable;

final class GetInvoiceOverviewHandler
{
    private const SESSION_PAGE_SIZE = 100;
    private const SESSION_PAGE_LIMIT = 10;
    private const INVOICE_PAGE_SIZE = 100;
    private const INVOICE_PAGE_LIMIT = 10;

    public function __construct(
        private readonly SubmittedInvoiceRepository $submittedInvoiceRepository,
        private readonly KsefApi $ksefApi,
        private readonly AccessTokenStore $accessTokenStore,
        private readonly AuthenticateHandlerInterface $authenticateHandler
    ) {}

    /**
     * @return list<array{
     *   sessionReferenceNumber:string,
     *   invoiceReferenceNumber:string,
     *   ksefNumber:string,
     *   invoiceNumber:string,
     *   submittedAt:string,
     *   invoiceStatusCode:int|null,
     *   invoiceStatusDescription:string,
     *   sessionStatusCode:int|null
     * }>
     */
    public function provide(): array
    {
        $accessToken = $this->accessTokenStore->get() ?? $this->authenticateHandler->execute()->accessToken;
        $rows = $this->fetchRowsFromKsef($accessToken->value);
        $rows = $this->mergeWithLocalRows($rows, $accessToken->value);

        return $rows;
    }

    /**
     * @return list<array{
     *   sessionReferenceNumber:string,
     *   invoiceReferenceNumber:string,
     *   ksefNumber:string,
     *   invoiceNumber:string,
     *   submittedAt:string,
     *   invoiceStatusCode:int|null,
     *   invoiceStatusDescription:string,
     *   sessionStatusCode:int|null
     * }>
     */
    private function fetchRowsFromKsef(string $accessToken): array
    {
        $rows = [];
        foreach (['Online', 'Batch'] as $sessionType) {
            $rows = [...$rows, ...$this->fetchRowsBySessionType($accessToken, $sessionType)];
        }

        return $rows;
    }

    /**
     * @param list<array{
     *   sessionReferenceNumber:string,
     *   invoiceReferenceNumber:string,
     *   ksefNumber:string,
     *   invoiceNumber:string,
     *   submittedAt:string,
     *   invoiceStatusCode:int|null,
     *   invoiceStatusDescription:string,
     *   sessionStatusCode:int|null
     * }> $rows
     * @return list<array{
     *   sessionReferenceNumber:string,
     *   invoiceReferenceNumber:string,
     *   ksefNumber:string,
     *   invoiceNumber:string,
     *   submittedAt:string,
     *   invoiceStatusCode:int|null,
     *   invoiceStatusDescription:string,
     *   sessionStatusCode:int|null
     * }>
     */
    private function mergeWithLocalRows(array $rows, string $accessToken): array
    {
        $seen = [];
        foreach ($rows as $row) {
            $seen[$row['sessionReferenceNumber'] . ':' . $row['invoiceReferenceNumber']] = true;
        }

        $submittedInvoices = $this->submittedInvoiceRepository->all();

        foreach ($submittedInvoices as $submittedInvoice) {
            $key = $submittedInvoice->sessionReferenceNumber . ':' . $submittedInvoice->invoiceReferenceNumber;
            if (isset($seen[$key])) {
                continue;
            }

            $rows[] = $this->buildRow($submittedInvoice, $accessToken);
            $seen[$key] = true;
        }

        usort(
            $rows,
            fn (array $a, array $b): int => $this->resolveTimestamp($b['submittedAt']) <=> $this->resolveTimestamp($a['submittedAt'])
        );

        return $rows;
    }

    /**
     * @return array{
     *   sessionReferenceNumber:string,
     *   invoiceReferenceNumber:string,
     *   ksefNumber:string,
     *   invoiceNumber:string,
     *   submittedAt:string,
     *   invoiceStatusCode:int|null,
     *   invoiceStatusDescription:string,
     *   sessionStatusCode:int|null
     * }
     */
    private function buildRow(SubmittedInvoice $submittedInvoice, string $accessToken): array
    {
        $invoiceStatusCode = null;
        $invoiceStatusDescription = 'Brak danych';
        $sessionStatusCode = null;

        try {
            $invoiceStatus = $this->ksefApi->getSessionInvoiceStatus(
                $accessToken,
                $submittedInvoice->sessionReferenceNumber,
                $submittedInvoice->invoiceReferenceNumber
            );
            $invoiceStatusCode = isset($invoiceStatus['status']['code']) ? (int) $invoiceStatus['status']['code'] : null;
            $invoiceStatusDescription = isset($invoiceStatus['status']['description'])
                ? (string) $invoiceStatus['status']['description']
                : 'Brak opisu';
        } catch (Throwable $exception) {
            $invoiceStatusDescription = 'Błąd pobierania statusu: ' . $exception->getMessage();
        }

        try {
            $sessionStatus = $this->ksefApi->getSessionStatus($accessToken, $submittedInvoice->sessionReferenceNumber);
            $sessionStatusCode = isset($sessionStatus['status']['code']) ? (int) $sessionStatus['status']['code'] : null;
        } catch (Throwable) {
            $sessionStatusCode = null;
        }

        return [
            'sessionReferenceNumber' => $submittedInvoice->sessionReferenceNumber,
            'invoiceReferenceNumber' => $submittedInvoice->invoiceReferenceNumber,
            'ksefNumber' => 'n/d',
            'invoiceNumber' => 'n/d',
            'submittedAt' => $submittedInvoice->submittedAt,
            'invoiceStatusCode' => $invoiceStatusCode,
            'invoiceStatusDescription' => $invoiceStatusDescription,
            'sessionStatusCode' => $sessionStatusCode,
        ];
    }

    /**
     * @return list<array{
     *   sessionReferenceNumber:string,
     *   invoiceReferenceNumber:string,
     *   ksefNumber:string,
     *   invoiceNumber:string,
     *   submittedAt:string,
     *   invoiceStatusCode:int|null,
     *   invoiceStatusDescription:string,
     *   sessionStatusCode:int|null
     * }>
     */
    private function fetchRowsBySessionType(string $accessToken, string $sessionType): array
    {
        $rows = [];
        $continuationToken = null;

        for ($page = 0; $page < self::SESSION_PAGE_LIMIT; $page++) {
            try {
                $sessionsData = $this->ksefApi->querySessions(
                    $accessToken,
                    $sessionType,
                    self::SESSION_PAGE_SIZE,
                    $continuationToken
                );
            } catch (Throwable) {
                break;
            }

            $sessions = isset($sessionsData['sessions']) && is_array($sessionsData['sessions']) ? $sessionsData['sessions'] : [];
            foreach ($sessions as $session) {
                if (!is_array($session)) {
                    continue;
                }

                $sessionReferenceNumber = (string) ($session['referenceNumber'] ?? '');
                if ($sessionReferenceNumber === '') {
                    continue;
                }

                $sessionStatusCode = isset($session['status']['code']) ? (int) $session['status']['code'] : null;
                $sessionDateCreated = (string) ($session['dateCreated'] ?? '');

                $rows = [...$rows, ...$this->fetchSessionInvoices(
                    $accessToken,
                    $sessionReferenceNumber,
                    $sessionStatusCode,
                    $sessionDateCreated
                )];
            }

            $continuationToken = $this->resolveContinuationToken($sessionsData);
            if ($continuationToken === null) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{
     *   sessionReferenceNumber:string,
     *   invoiceReferenceNumber:string,
     *   ksefNumber:string,
     *   invoiceNumber:string,
     *   submittedAt:string,
     *   invoiceStatusCode:int|null,
     *   invoiceStatusDescription:string,
     *   sessionStatusCode:int|null
     * }>
     */
    private function fetchSessionInvoices(
        string $accessToken,
        string $sessionReferenceNumber,
        ?int $sessionStatusCode,
        string $sessionDateCreated
    ): array {
        $rows = [];
        $continuationToken = null;

        for ($page = 0; $page < self::INVOICE_PAGE_LIMIT; $page++) {
            try {
                $invoiceList = $this->ksefApi->getSessionInvoices(
                    $accessToken,
                    $sessionReferenceNumber,
                    self::INVOICE_PAGE_SIZE,
                    $continuationToken
                );
            } catch (Throwable) {
                break;
            }

            $invoices = isset($invoiceList['invoices']) && is_array($invoiceList['invoices']) ? $invoiceList['invoices'] : [];
            foreach ($invoices as $invoice) {
                if (!is_array($invoice)) {
                    continue;
                }

                $invoiceReferenceNumber = (string) ($invoice['referenceNumber'] ?? '');
                if ($invoiceReferenceNumber === '') {
                    continue;
                }

                $rows[] = [
                    'sessionReferenceNumber' => $sessionReferenceNumber,
                    'invoiceReferenceNumber' => $invoiceReferenceNumber,
                    'ksefNumber' => (string) ($invoice['ksefNumber'] ?? 'n/d'),
                    'invoiceNumber' => (string) ($invoice['invoiceNumber'] ?? 'n/d'),
                    'submittedAt' => (string) ($invoice['permanentStorageDate'] ?? $invoice['invoicingDate'] ?? $invoice['acquisitionDate'] ?? $sessionDateCreated),
                    'invoiceStatusCode' => isset($invoice['status']['code']) ? (int) $invoice['status']['code'] : null,
                    'invoiceStatusDescription' => isset($invoice['status']['description'])
                        ? (string) $invoice['status']['description']
                        : 'Brak opisu',
                    'sessionStatusCode' => $sessionStatusCode,
                ];
            }

            $continuationToken = $this->resolveContinuationToken($invoiceList);
            if ($continuationToken === null) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function resolveContinuationToken(array $response): ?string
    {
        $token = $response['continuationToken'] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }

        return $token;
    }

    private function resolveTimestamp(string $date): int
    {
        $timestamp = strtotime($date);

        return $timestamp !== false ? $timestamp : 0;
    }
}
