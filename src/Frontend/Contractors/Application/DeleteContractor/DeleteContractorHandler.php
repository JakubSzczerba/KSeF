<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Application\DeleteContractor;

use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;

final class DeleteContractorHandler
{
    public function __construct(
        private readonly ContractorRepositoryInterface $repository
    ) {}

    public function handle(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
