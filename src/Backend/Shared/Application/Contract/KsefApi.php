<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Shared\Application\Contract;

interface KsefApi
{
    /**
     * @return array<string, mixed>
     */
    public function requestAuthChallenge(string $nip): array;

    /**
     * @return array<string, mixed>
     */
    public function initXadesSignatureAuthentication(string $signedXml): array;

    /**
     * @return array<string, mixed>
     */
    public function getAuthenticationStatus(string $referenceNumber, string $authenticationToken): array;

    /**
     * @return array<string, mixed>
     */
    public function redeemAuthenticationToken(string $authenticationToken): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getPublicKeyCertificates(): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function openOnlineSession(string $accessToken, array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendInvoice(string $accessToken, string $sessionReferenceNumber, array $payload): array;

    public function closeOnlineSession(string $accessToken, string $sessionReferenceNumber): void;

    /**
     * @return array<string, mixed>
     */
    public function getSessionInvoiceStatus(
        string $accessToken,
        string $sessionReferenceNumber,
        string $invoiceReferenceNumber
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getSessionStatus(string $accessToken, string $sessionReferenceNumber): array;

    /**
     * @return array<string, mixed>
     */
    public function querySessions(
        string $accessToken,
        string $sessionType,
        int $pageSize = 20,
        ?string $continuationToken = null
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getSessionInvoices(
        string $accessToken,
        string $sessionReferenceNumber,
        int $pageSize = 100,
        ?string $continuationToken = null
    ): array;

    /**
     * @return array{content:string,hash:string|null}
     */
    public function downloadInvoiceByKsefNumber(string $accessToken, string $ksefNumber): array;
}
