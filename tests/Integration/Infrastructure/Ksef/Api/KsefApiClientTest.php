<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Integration\Infrastructure\Ksef\Api;

use Ksef\Api\Infrastructure\Ksef\Api\KsefApiClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KsefApiClientTest extends TestCase
{
    #[Test]
    public function shouldSendChallengeRequestWithExpectedPayload(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedOptions = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedOptions): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedOptions = $options;

            return new MockResponse(json_encode([
                'challenge' => 'abc',
                'timestamp' => '2026-02-16T00:00:00+00:00',
                'timestampMs' => 1771200000000,
            ], JSON_THROW_ON_ERROR));
        });

        $client = new KsefApiClient($httpClient);
        $result = $client->requestAuthChallenge('1234567890');
        $requestPayload = $this->extractJsonPayload($capturedOptions);

        self::assertSame('POST', $capturedMethod);
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/auth/challenge', $capturedUrl);
        self::assertSame('Nip', $requestPayload['contextIdentifier']['type'] ?? null);
        self::assertSame('1234567890', $requestPayload['contextIdentifier']['value'] ?? null);
        self::assertSame('abc', $result['challenge']);
    }

    #[Test]
    public function shouldSendXadesXmlRequest(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedOptions = [];
        $xml = '<AuthTokenRequest xmlns="http://ksef.mf.gov.pl/auth/token/2.0"></AuthTokenRequest>';

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedOptions): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedOptions = $options;

            return new MockResponse(json_encode([
                'referenceNumber' => 'ref-1',
                'authenticationToken' => ['token' => 'auth-token'],
            ], JSON_THROW_ON_ERROR));
        });

        $client = new KsefApiClient($httpClient);
        $result = $client->initXadesSignatureAuthentication($xml);

        self::assertSame('POST', $capturedMethod);
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/auth/xades-signature', $capturedUrl);
        self::assertSame($xml, $capturedOptions['body']);
        self::assertSame('ref-1', $result['referenceNumber']);
    }

    #[Test]
    public function shouldSendInvoiceToSessionEndpointWithAuthorizationHeader(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedOptions = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedOptions): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedOptions = $options;

            return new MockResponse(json_encode([
                'referenceNumber' => 'inv-ref-1',
            ], JSON_THROW_ON_ERROR));
        });

        $client = new KsefApiClient($httpClient);
        $payload = [
            'invoiceHash' => 'h1',
            'invoiceSize' => 10,
            'encryptedInvoiceHash' => 'h2',
            'encryptedInvoiceSize' => 12,
            'encryptedInvoiceContent' => 'abc',
            'offlineMode' => false,
        ];

        $result = $client->sendInvoice('access-token', '2026-SO-1', $payload);
        $requestPayload = $this->extractJsonPayload($capturedOptions);

        self::assertSame('POST', $capturedMethod);
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/sessions/online/2026-SO-1/invoices', $capturedUrl);
        self::assertSame($payload, $requestPayload);
        self::assertSame('inv-ref-1', $result['referenceNumber']);
    }

    #[Test]
    public function shouldQuerySessionsWithTypeAndContinuationTokenHeader(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedOptions = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedOptions): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedOptions = $options;

            return new MockResponse(json_encode([
                'continuationToken' => 'next-token',
                'sessions' => [],
            ], JSON_THROW_ON_ERROR));
        });

        $client = new KsefApiClient($httpClient);
        $result = $client->querySessions('access-token', 'Batch', 150, 'continuation-token');

        self::assertSame('GET', $capturedMethod);
        self::assertStringStartsWith('https://api-test.ksef.mf.gov.pl/v2/sessions', (string) $capturedUrl);
        self::assertSame('Batch', $capturedOptions['query']['sessionType'] ?? null);
        self::assertSame(150, $capturedOptions['query']['pageSize'] ?? null);
        self::assertSame(
            'x-continuation-token: continuation-token',
            $capturedOptions['normalized_headers']['x-continuation-token'][0] ?? null
        );
        self::assertSame('next-token', $result['continuationToken']);
    }

    #[Test]
    public function shouldQuerySessionInvoicesWithContinuationTokenHeader(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedOptions = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedOptions): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedOptions = $options;

            return new MockResponse(json_encode([
                'invoices' => [],
            ], JSON_THROW_ON_ERROR));
        });

        $client = new KsefApiClient($httpClient);
        $client->getSessionInvoices('access-token', '2026-SO-1', 200, 'continuation-token');

        self::assertSame('GET', $capturedMethod);
        self::assertStringStartsWith(
            'https://api-test.ksef.mf.gov.pl/v2/sessions/2026-SO-1/invoices',
            (string) $capturedUrl
        );
        self::assertSame(200, $capturedOptions['query']['pageSize'] ?? null);
        self::assertSame(
            'x-continuation-token: continuation-token',
            $capturedOptions['normalized_headers']['x-continuation-token'][0] ?? null
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function extractJsonPayload(array $options): array
    {
        if (isset($options['json']) && is_array($options['json'])) {
            return $options['json'];
        }

        $body = $options['body'] ?? null;
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
