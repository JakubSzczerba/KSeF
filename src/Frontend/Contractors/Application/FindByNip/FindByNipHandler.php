<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Application\FindByNip;

use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;
use Ksef\Frontend\Contractors\Domain\Contractor;

final class FindByNipHandler
{
    public function __construct(
        private readonly ContractorRepositoryInterface $repository
    ) {}

    public function handle(string $nip): ?Contractor
    {
        return $this->repository->findByNip($nip);
    }
}
