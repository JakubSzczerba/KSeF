<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Application\Contract;

use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;

interface SubmittedInvoiceRepositoryInterface
{
    public function add(SubmittedInvoice $submittedInvoice): void;

    /**
     * @return list<SubmittedInvoice>
     */
    public function all(): array;

    /**
     * @return array{sentThisMonth: int, unpaidCount: int, overdueCount: int, paidRevenue: float|null}
     */
    public function getStats(): array;

    public function updatePaymentStatus(string $invoiceRef, string $paymentStatus): bool;

    /**
     * @return array{items: list<SubmittedInvoice>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $paymentStatus = null): array;
}
