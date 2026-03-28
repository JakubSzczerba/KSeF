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
}
