<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Application\UpdateContractor;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;
use Ksef\Frontend\Contractors\Domain\Contractor;

final class UpdateContractorHandler
{
    public function __construct(
        private readonly ContractorRepositoryInterface $repository
    ) {}

    public function handle(UpdateContractorCommand $command): bool
    {
        if (trim($command->name) === '') {
            throw new DomainValidationException('Nazwa kontrahenta jest wymagana.');
        }

        $existing = $this->repository->findById($command->id);
        if (null === $existing) {
            return false;
        }

        return $this->repository->update(new Contractor(
            $command->id,
            $command->name,
            $existing->nip,
            $command->address,
            $command->email,
            $existing->createdAt
        ));
    }
}
