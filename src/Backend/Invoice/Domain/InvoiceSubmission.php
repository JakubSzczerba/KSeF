<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Domain;

use Ksef\Backend\Invoice\Domain\ValueObject\InvoiceReferenceNumber;
use Ksef\Backend\Invoice\Domain\ValueObject\SessionReferenceNumber;

final readonly class InvoiceSubmission
{
    /**
     * @param array<string, mixed> $invoiceStatus
     * @param array<string, mixed> $sessionStatus
     */
    public function __construct(
        public SessionReferenceNumber $sessionReferenceNumber,
        public InvoiceReferenceNumber $invoiceReferenceNumber,
        public array $invoiceStatus,
        public array $sessionStatus,
        public ?string $closeSessionError
    ) {}
}
