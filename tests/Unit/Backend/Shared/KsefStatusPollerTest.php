<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Backend\Shared;

use Ksef\Backend\Shared\Application\Exception\OperationTimeoutException;
use Ksef\Backend\Shared\Application\KsefStatusPoller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KsefStatusPollerTest extends TestCase
{
    #[Test]
    public function shouldReturnOnFirstTerminalCode(): void
    {
        $poller = new KsefStatusPoller(0);
        $calls = 0;

        $result = $poller->pollUntilTerminal(
            function () use (&$calls): array {
                $calls++;
                return ['status' => ['code' => 200]];
            },
            [200, 400],
            5,
            'Timeout.'
        );

        self::assertSame(1, $calls);
        self::assertSame(200, $result['status']['code']);
    }

    #[Test]
    public function shouldRetryUntilTerminalCode(): void
    {
        $poller = new KsefStatusPoller(0);
        $responses = [
            ['status' => ['code' => 100]],
            ['status' => ['code' => 100]],
            ['status' => ['code' => 200]],
        ];
        $index = 0;

        $result = $poller->pollUntilTerminal(
            function () use (&$responses, &$index): array {
                return $responses[$index++];
            },
            [200],
            5,
            'Timeout.'
        );

        self::assertSame(3, $index);
        self::assertSame(200, $result['status']['code']);
    }

    #[Test]
    public function shouldThrowOperationTimeoutException(): void
    {
        $poller = new KsefStatusPoller(0);

        $this->expectException(OperationTimeoutException::class);
        $this->expectExceptionMessage('Timeout testowy.');

        $poller->pollUntilTerminal(
            fn (): array => ['status' => ['code' => 100]],
            [200],
            3,
            'Timeout testowy.'
        );
    }
}
