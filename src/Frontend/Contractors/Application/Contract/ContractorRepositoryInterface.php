<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Application\Contract;

use Ksef\Frontend\Contractors\Domain\Contractor;

interface ContractorRepositoryInterface
{
    public function add(Contractor $contractor): Contractor;

    public function update(Contractor $contractor): bool;

    public function delete(int $id): bool;

    public function findById(int $id): ?Contractor;

    public function findByNip(string $nip): ?Contractor;

    /**
     * @return array{items: list<Contractor>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array;
}
