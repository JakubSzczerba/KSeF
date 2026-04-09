<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Settings\Application\UpdateCompanySettings;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Settings\Application\Contract\CompanySettingsRepositoryInterface;
use Ksef\Frontend\Settings\Domain\CompanySettings;

final class UpdateCompanySettingsHandler
{
    public function __construct(
        private readonly CompanySettingsRepositoryInterface $repository
    ) {}

    public function handle(UpdateCompanySettingsCommand $command): CompanySettings
    {
        $name = trim($command->name);
        if ($name === '') {
            throw new DomainValidationException('Nazwa firmy jest wymagana.');
        }

        $nip = trim($command->nip);
        if (!preg_match('/^\d{10}$/', $nip)) {
            throw new DomainValidationException('NIP musi składać się z 10 cyfr.');
        }

        $settings = new CompanySettings(
            id: 0,
            name: $name,
            nip: $nip,
            address: $command->address,
            bankAccount: $command->bankAccount,
            vatNumber: $command->vatNumber,
        );

        return $this->repository->save($settings);
    }
}
