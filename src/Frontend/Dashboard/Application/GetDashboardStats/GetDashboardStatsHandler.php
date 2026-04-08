<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Application\GetDashboardStats;

use Ksef\Frontend\Dashboard\Application\Contract\SubmittedInvoiceRepositoryInterface;

final class GetDashboardStatsHandler
{
    public function __construct(
        private readonly SubmittedInvoiceRepositoryInterface $repository
    ) {}

    /**
     * @return array{sentThisMonth: int, unpaidCount: int, overdueCount: int, paidRevenue: float|null}
     */
    public function provide(): array
    {
        return $this->repository->getStats();
    }
}
