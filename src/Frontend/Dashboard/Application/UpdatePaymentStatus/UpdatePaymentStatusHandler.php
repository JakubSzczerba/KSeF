<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Application\UpdatePaymentStatus;

use Ksef\Frontend\Dashboard\Application\Contract\SubmittedInvoiceRepositoryInterface;

final class UpdatePaymentStatusHandler
{
    private const array ALLOWED_STATUSES = ['unpaid', 'paid', 'overdue'];

    public function __construct(
        private readonly SubmittedInvoiceRepositoryInterface $repository
    ) {}

    public function handle(string $invoiceRef, string $paymentStatus): bool
    {
        if (!in_array($paymentStatus, self::ALLOWED_STATUSES, true)) {
            return false;
        }

        return $this->repository->updatePaymentStatus($invoiceRef, $paymentStatus);
    }
}
