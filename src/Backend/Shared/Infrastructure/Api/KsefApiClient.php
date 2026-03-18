<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Shared\Infrastructure\Api;

use Ksef\Backend\Shared\Application\Contract\KsefApi;
use Ksef\Backend\Shared\Infrastructure\Exception\ApiClientException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class KsefApiClient implements KsefApi
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private readonly string $apiUrl
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function requestAuthChallenge(string $nip): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl . '/auth/challenge', [
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
        $response = $this->httpClient->request('POST', $this->apiUrl . '/auth/xades-signature', [
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
        $response = $this->httpClient->request('GET', $this->apiUrl . '/auth/' . rawurlencode($referenceNumber), [
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
        $response = $this->httpClient->request('POST', $this->apiUrl . '/auth/token/redeem', [
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
        $response = $this->httpClient->request('GET', $this->apiUrl . '/security/public-key-certificates');

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
        $response = $this->httpClient->request('POST', $this->apiUrl . '/sessions/online', [
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
            $this->apiUrl . '/sessions/online/' . rawurlencode($sessionReferenceNumber) . '/invoices',
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
            $this->apiUrl . '/sessions/online/' . rawurlencode($sessionReferenceNumber) . '/close',
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
            $this->apiUrl . '/sessions/' . rawurlencode($sessionReferenceNumber) . '/invoices/' . rawurlencode($invoiceReferenceNumber),
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
            $this->apiUrl . '/sessions/' . rawurlencode($sessionReferenceNumber),
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
    public function querySessions(
        string $accessToken,
        string $sessionType,
        int $pageSize = 20,
        ?string $continuationToken = null
    ): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];
        if (is_string($continuationToken) && $continuationToken !== '') {
            $headers['x-continuation-token'] = $continuationToken;
        }

        $response = $this->httpClient->request('GET', $this->apiUrl . '/sessions', [
            'headers' => [
                ...$headers,
            ],
            'query' => [
                'sessionType' => $sessionType,
                'pageSize' => max(10, min(1000, $pageSize)),
            ],
        ]);

        return $this->safeToArray($response, 'pobierania listy sesji');
    }

    /**
     * @return array<string, mixed>
     */
    public function getSessionInvoices(
        string $accessToken,
        string $sessionReferenceNumber,
        int $pageSize = 100,
        ?string $continuationToken = null
    ): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];
        if (is_string($continuationToken) && $continuationToken !== '') {
            $headers['x-continuation-token'] = $continuationToken;
        }

        $response = $this->httpClient->request(
            'GET',
            $this->apiUrl . '/sessions/' . rawurlencode($sessionReferenceNumber) . '/invoices',
            [
                'headers' => [
                    ...$headers,
                ],
                'query' => [
                    'pageSize' => max(10, min(1000, $pageSize)),
                ],
            ]
        );

        return $this->safeToArray($response, 'pobierania faktur sesji');
    }

    /**
     * @return array{content:string,hash:string|null}
     */
    public function downloadInvoiceByKsefNumber(string $accessToken, string $ksefNumber): array
    {
        $response = $this->httpClient->request(
            'GET',
            $this->apiUrl . '/invoices/ksef/' . rawurlencode($ksefNumber),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/xml',
                ],
            ]
        );

        try {
            $content = $response->getContent();
        } catch (ClientExceptionInterface $e) {
            $body = $e->getResponse()->getContent(false);
            throw new ApiClientException(
                'Błąd API podczas pobierania faktury po numerze KSeF: ' . $body,
                0,
                $e
            );
        }

        $headers = $response->getHeaders(false);
        $hash = null;
        if (isset($headers['x-ms-meta-hash'][0])) {
            $hash = $headers['x-ms-meta-hash'][0];
        }

        return [
            'content' => $content,
            'hash' => $hash,
        ];
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
            throw new ApiClientException(
                'Błąd API podczas ' . $operationName . ': ' . $body,
                0,
                $e
            );
        }
    }
}
