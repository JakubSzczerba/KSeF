<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Application\ListContractors;

use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;
use Ksef\Frontend\Contractors\Domain\Contractor;

final class ListContractorsHandler
{
    public function __construct(
        private readonly ContractorRepositoryInterface $repository
    ) {}

    /**
     * @return array{items: list<Contractor>, total: int}
     */
    public function handle(int $page, int $limit, ?string $search = null): array
    {
        return $this->repository->paginate($page, $limit, $search);
    }
}
