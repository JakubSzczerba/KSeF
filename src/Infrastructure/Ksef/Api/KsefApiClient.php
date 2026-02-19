<?php

declare(strict_types=1);

namespace Ksef\Infrastructure\Ksef\Api;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class KsefApiClient
{
    private const API_URL = 'https://api-test.ksef.mf.gov.pl/v2';

    public function __construct(private HttpClientInterface $httpClient) {}

    /**
     * @return array<string, mixed>
     */
    public function requestAuthChallenge(string $nip): array
    {
        $response = $this->httpClient->request('POST', self::API_URL . '/auth/challenge', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'contextIdentifier' => [
                    'type' => 'Nip',
                    'value' => $nip,
                ],
            ],
        ]);

        return $this->safeToArray($response, 'pobierania challenge');
    }

    /**
     * @return array<string, mixed>
     */
    public function initXadesSignatureAuthentication(string $signedXml): array
    {
        $response = $this->httpClient->request('POST', self::API_URL . '/auth/xades-signature', [
            'headers' => [
                'Content-Type' => 'application/xml',
            ],
            'body' => $signedXml,
        ]);

        return $this->safeToArray($response, 'inicjalizacji uwierzytelnienia XAdES');
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuthenticationStatus(string $referenceNumber, string $authenticationToken): array
    {
        $response = $this->httpClient->request('GET', self::API_URL . '/auth/' . rawurlencode($referenceNumber), [
            'headers' => [
                'Authorization' => 'Bearer ' . $authenticationToken,
            ],
        ]);

        return $this->safeToArray($response, 'pobierania statusu uwierzytelnienia');
    }

    /**
     * @return array<string, mixed>
     */
    public function redeemAuthenticationToken(string $authenticationToken): array
    {
        $response = $this->httpClient->request('POST', self::API_URL . '/auth/token/redeem', [
            'headers' => [
                'Authorization' => 'Bearer ' . $authenticationToken,
            ],
        ]);

        return $this->safeToArray($response, 'pobierania tokenów dostępowych');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPublicKeyCertificates(): array
    {
        $response = $this->httpClient->request('GET', self::API_URL . '/security/public-key-certificates');

        /** @var list<array<string, mixed>> $data */
        $data = $this->safeToArray($response, 'pobierania certyfikatów klucza publicznego');

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function openOnlineSession(string $accessToken, array $payload): array
    {
        $response = $this->httpClient->request('POST', self::API_URL . '/sessions/online', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $this->safeToArray($response, 'otwarcia sesji online');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendInvoice(string $accessToken, string $sessionReferenceNumber, array $payload): array
    {
        $response = $this->httpClient->request(
            'POST',
            self::API_URL . '/sessions/online/' . rawurlencode($sessionReferenceNumber) . '/invoices',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]
        );

        return $this->safeToArray($response, 'wysyłki faktury');
    }

    public function closeOnlineSession(string $accessToken, string $sessionReferenceNumber): void
    {
        $response = $this->httpClient->request(
            'POST',
            self::API_URL . '/sessions/online/' . rawurlencode($sessionReferenceNumber) . '/close',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        $response->getStatusCode();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSessionInvoiceStatus(
        string $accessToken,
        string $sessionReferenceNumber,
        string $invoiceReferenceNumber
    ): array {
        $response = $this->httpClient->request(
            'GET',
            self::API_URL . '/sessions/' . rawurlencode($sessionReferenceNumber) . '/invoices/' . rawurlencode($invoiceReferenceNumber),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        return $this->safeToArray($response, 'pobierania statusu faktury');
    }

    /**
     * @return array<string, mixed>
     */
    public function getSessionStatus(string $accessToken, string $sessionReferenceNumber): array
    {
        $response = $this->httpClient->request(
            'GET',
            self::API_URL . '/sessions/' . rawurlencode($sessionReferenceNumber),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        return $this->safeToArray($response, 'pobierania statusu sesji');
    }

    /**
     * @return array<string, mixed>
     */
    private function safeToArray(ResponseInterface $response, string $operationName): array
    {
        try {
            return $response->toArray();
        } catch (ClientExceptionInterface $e) {
            $body = $e->getResponse()->getContent(false);
            throw new \RuntimeException(
                'Błąd API podczas ' . $operationName . ': ' . $body,
                0,
                $e
            );
        }
    }
}
