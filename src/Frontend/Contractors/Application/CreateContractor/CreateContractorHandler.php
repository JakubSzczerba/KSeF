<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Application\CreateContractor;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;
use Ksef\Frontend\Contractors\Domain\Contractor;

final class CreateContractorHandler
{
    public function __construct(
        private readonly ContractorRepositoryInterface $repository
    ) {}

    public function handle(CreateContractorCommand $command): Contractor
    {
        $this->validate($command);

        $existing = $this->repository->findByNip($command->nip);
        if (null !== $existing) {
            throw new DomainValidationException(sprintf('Kontrahent z NIP "%s" już istnieje.', $command->nip));
        }

        return $this->repository->add(new Contractor(
            0,
            $command->name,
            $command->nip,
            $command->address,
            $command->email,
            ''
        ));
    }

    private function validate(CreateContractorCommand $command): void
    {
        if (trim($command->name) === '') {
            throw new DomainValidationException('Nazwa kontrahenta jest wymagana.');
        }

        if (!preg_match('/^\d{10}$/', $command->nip)) {
            throw new DomainValidationException('NIP musi składać się z dokładnie 10 cyfr.');
        }
    }
}
