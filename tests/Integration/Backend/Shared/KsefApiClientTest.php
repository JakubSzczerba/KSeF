<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Integration\Backend\Shared;

use Ksef\Backend\Shared\Infrastructure\Api\KsefApiClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KsefApiClientTest extends TestCase
{
    #[Test]
    public function shouldSendChallengeRequestWithExpectedPayload(): void
    {
        [$client, $capture] = $this->makeCapturingClient(new MockResponse(json_encode([
            'challenge' => 'abc',
            'timestamp' => '2026-02-16T00:00:00+00:00',
            'timestampMs' => 1771200000000,
        ], JSON_THROW_ON_ERROR)));

        $result = $client->requestAuthChallenge('1234567890');
        $payload = $this->extractJsonPayload($capture->options);

        self::assertSame('POST', $capture->method);
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/auth/challenge', $capture->url);
        self::assertSame('Nip', $payload['contextIdentifier']['type'] ?? null);
        self::assertSame('1234567890', $payload['contextIdentifier']['value'] ?? null);
        self::assertSame('abc', $result['challenge']);
    }

    #[Test]
    public function shouldSendXadesXmlBodyToAuthEndpoint(): void
    {
        $xml = '<AuthTokenRequest xmlns="http://ksef.mf.gov.pl/auth/token/2.0"></AuthTokenRequest>';

        [$client, $capture] = $this->makeCapturingClient(new MockResponse(json_encode([
            'referenceNumber' => 'ref-1',
            'authenticationToken' => ['token' => 'auth-token'],
        ], JSON_THROW_ON_ERROR)));

        $result = $client->initXadesSignatureAuthentication($xml);

        self::assertSame('POST', $capture->method);
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/auth/xades-signature', $capture->url);
        self::assertSame($xml, $capture->options['body']);
        self::assertSame('ref-1', $result['referenceNumber']);
    }

    #[Test]
    public function shouldSendInvoiceToSessionEndpointWithAuthorizationHeader(): void
    {
        [$client, $capture] = $this->makeCapturingClient(new MockResponse(json_encode([
            'referenceNumber' => 'inv-ref-1',
        ], JSON_THROW_ON_ERROR)));

        $payload = [
            'invoiceHash' => 'h1',
            'invoiceSize' => 10,
            'encryptedInvoiceHash' => 'h2',
            'encryptedInvoiceSize' => 12,
            'encryptedInvoiceContent' => 'abc',
            'offlineMode' => false,
        ];

        $result = $client->sendInvoice('access-token', '2026-SO-1', $payload);

        self::assertSame('POST', $capture->method);
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/sessions/online/2026-SO-1/invoices', $capture->url);
        self::assertSame($payload, $this->extractJsonPayload($capture->options));
        self::assertSame('inv-ref-1', $result['referenceNumber']);
    }

    #[Test]
    public function shouldQuerySessionsWithTypeAndContinuationTokenHeader(): void
    {
        [$client, $capture] = $this->makeCapturingClient(new MockResponse(json_encode([
            'continuationToken' => 'next-token',
            'sessions' => [],
        ], JSON_THROW_ON_ERROR)));

        $result = $client->querySessions('access-token', 'Batch', 150, 'continuation-token');

        self::assertSame('GET', $capture->method);
        self::assertStringStartsWith('https://api-test.ksef.mf.gov.pl/v2/sessions', (string) $capture->url);
        self::assertSame('Batch', $capture->options['query']['sessionType'] ?? null);
        self::assertSame(150, $capture->options['query']['pageSize'] ?? null);
        self::assertSame(
            'x-continuation-token: continuation-token',
            $capture->options['normalized_headers']['x-continuation-token'][0] ?? null
        );
        self::assertSame('next-token', $result['continuationToken']);
    }

    #[Test]
    public function shouldQuerySessionInvoicesWithContinuationTokenHeader(): void
    {
        [$client, $capture] = $this->makeCapturingClient(new MockResponse(json_encode([
            'invoices' => [],
        ], JSON_THROW_ON_ERROR)));

        $client->getSessionInvoices('access-token', '2026-SO-1', 200, 'continuation-token');

        self::assertSame('GET', $capture->method);
        self::assertStringStartsWith(
            'https://api-test.ksef.mf.gov.pl/v2/sessions/2026-SO-1/invoices',
            (string) $capture->url
        );
        self::assertSame(200, $capture->options['query']['pageSize'] ?? null);
        self::assertSame(
            'x-continuation-token: continuation-token',
            $capture->options['normalized_headers']['x-continuation-token'][0] ?? null
        );
    }

    #[Test]
    public function shouldDownloadInvoiceXmlByKsefNumber(): void
    {
        [$client, $capture] = $this->makeCapturingClient(new MockResponse('<Faktura/>', [
            'response_headers' => [
                'x-ms-meta-hash: abc123',
                'content-type: application/xml',
            ],
        ]));

        $result = $client->downloadInvoiceByKsefNumber('access-token', '5555555555-20250828-010080615740-E4');

        self::assertSame('GET', $capture->method);
        self::assertSame(
            'https://api-test.ksef.mf.gov.pl/v2/invoices/ksef/5555555555-20250828-010080615740-E4',
            $capture->url
        );
        self::assertSame('<Faktura/>', $result['content']);
        self::assertSame('abc123', $result['hash']);
        self::assertSame(
            'Authorization: Bearer access-token',
            $capture->options['normalized_headers']['authorization'][0] ?? null
        );
    }

    /**
     * @return array{0: KsefApiClient, 1: \stdClass}
     */
    private function makeCapturingClient(MockResponse $response): array
    {
        $capture = new \stdClass();
        $capture->method = '';
        $capture->url = '';
        $capture->options = [];

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use ($capture, $response): MockResponse {
                $capture->method = $method;
                $capture->url = $url;
                $capture->options = $options;

                return $response;
            }
        );

        return [new KsefApiClient($httpClient, 'https://api-test.ksef.mf.gov.pl/v2'), $capture];
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
