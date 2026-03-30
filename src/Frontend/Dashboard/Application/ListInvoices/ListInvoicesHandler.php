<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Application\ListInvoices;

use Ksef\Frontend\Dashboard\Application\Contract\SubmittedInvoiceRepositoryInterface;
use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;

final class ListInvoicesHandler
{
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly SubmittedInvoiceRepositoryInterface $repository
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int, limit: int}
     */
    public function provide(int $page, int $limit, ?string $paymentStatus): array
    {
        $page = max(1, $page);
        $limit = min(max(1, $limit), self::MAX_LIMIT);

        $result = $this->repository->paginate($page, $limit, $paymentStatus);

        $total = $result['total'];
        $pages = (int) ceil($total / $limit);

        $items = array_map(
            static fn (SubmittedInvoice $invoice): array => $invoice->toArray(),
            $result['items']
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, $pages),
            'limit' => $limit,
        ];
    }
}
