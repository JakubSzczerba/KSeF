<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Infrastructure\Persistence;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'submitted_invoices')]
#[ORM\Index(columns: ['submitted_at'], name: 'idx_submitted_invoices_submitted_at')]
#[ORM\UniqueConstraint(name: 'uq_session_invoice_ref', columns: ['session_ref', 'invoice_ref'])]
class SubmittedInvoiceEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(name: 'session_ref', type: 'string', length: 255)]
    private string $sessionRef;

    #[ORM\Column(name: 'invoice_ref', type: 'string', length: 255)]
    private string $invoiceRef;

    #[ORM\Column(name: 'submitted_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $submittedAt;

    #[ORM\Column(name: 'payment_status', type: 'string', length: 20, options: ['default' => 'unpaid'])]
    private string $paymentStatus;

    #[ORM\Column(name: 'amount', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $amount;

    public function __construct(
        string $sessionRef,
        string $invoiceRef,
        DateTimeImmutable $submittedAt,
        string $paymentStatus = 'unpaid',
        ?float $amount = null
    ) {
        $this->sessionRef = $sessionRef;
        $this->invoiceRef = $invoiceRef;
        $this->submittedAt = $submittedAt;
        $this->paymentStatus = $paymentStatus;
        $this->amount = null !== $amount ? (string) $amount : null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionRef(): string
    {
        return $this->sessionRef;
    }

    public function getInvoiceRef(): string
    {
        return $this->invoiceRef;
    }

    public function getSubmittedAt(): DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): void
    {
        $this->paymentStatus = $paymentStatus;
    }

    public function getAmount(): ?float
    {
        return null !== $this->amount ? (float) $this->amount : null;
    }
}
