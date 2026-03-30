<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Application\Dto;

final readonly class SendInvoiceJobStatus
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public string $status,
        public ?string $ksefNumber = null,
        public ?string $error = null
    ) {}

    /**
     * @return array{status: string, ksefNumber: string|null, error: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'ksefNumber' => $this->ksefNumber,
            'error' => $this->error,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['status'] ?? self::STATUS_PENDING),
            isset($data['ksefNumber']) ? (string) $data['ksefNumber'] : null,
            isset($data['error']) ? (string) $data['error'] : null,
        );
    }
}
