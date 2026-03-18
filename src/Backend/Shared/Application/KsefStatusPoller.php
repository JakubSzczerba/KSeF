<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Shared\Application;

use Ksef\Backend\Shared\Application\Exception\OperationTimeoutException;

final class KsefStatusPoller
{
    public function __construct(private readonly int $intervalSeconds = 2) {}

    /**
     * @param callable(): array<string, mixed> $fetchStatus
     * @param list<int> $terminalCodes
     * @return array<string, mixed>
     */
    public function pollUntilTerminal(
        callable $fetchStatus,
        array $terminalCodes,
        int $maxAttempts,
        string $timeoutMessage
    ): array {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($attempt > 0) {
                sleep($this->intervalSeconds);
            }

            $status = $fetchStatus();
            $statusCode = (int) ($status['status']['code'] ?? 0);

            if (in_array($statusCode, $terminalCodes, true)) {
                return $status;
            }
        }

        throw new OperationTimeoutException($timeoutMessage);
    }
}
