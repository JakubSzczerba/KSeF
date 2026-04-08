<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Domain;

final readonly class SubmittedInvoice
{
    public function __construct(
        public string $sessionReferenceNumber,
        public string $invoiceReferenceNumber,
        public string $submittedAt,
        public string $paymentStatus = 'unpaid',
        public ?float $amount = null
    ) {}

    /**
     * @return array{sessionReferenceNumber:string,invoiceReferenceNumber:string,submittedAt:string,paymentStatus:string}
     */
    public function toArray(): array
    {
        return [
            'sessionReferenceNumber' => $this->sessionReferenceNumber,
            'invoiceReferenceNumber' => $this->invoiceReferenceNumber,
            'submittedAt' => $this->submittedAt,
            'paymentStatus' => $this->paymentStatus,
        ];
    }

    /**
     * @param array{sessionReferenceNumber?:mixed,invoiceReferenceNumber?:mixed,submittedAt?:mixed,paymentStatus?:mixed} $data
     */
    public static function fromArray(array $data): ?self
    {
        $sessionReferenceNumber = isset($data['sessionReferenceNumber']) ? (string) $data['sessionReferenceNumber'] : '';
        $invoiceReferenceNumber = isset($data['invoiceReferenceNumber']) ? (string) $data['invoiceReferenceNumber'] : '';
        $submittedAt = isset($data['submittedAt']) ? (string) $data['submittedAt'] : '';

        if ($sessionReferenceNumber === '' || $invoiceReferenceNumber === '' || $submittedAt === '') {
            return null;
        }

        return new self(
            $sessionReferenceNumber,
            $invoiceReferenceNumber,
            $submittedAt,
            isset($data['paymentStatus']) ? (string) $data['paymentStatus'] : 'unpaid'
        );
    }
}
