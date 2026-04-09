<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Settings\Application\GetCompanySettings;

use Ksef\Frontend\Settings\Application\Contract\CompanySettingsRepositoryInterface;
use Ksef\Frontend\Settings\Domain\CompanySettings;

final class GetCompanySettingsHandler
{
    public function __construct(
        private readonly CompanySettingsRepositoryInterface $repository
    ) {}

    public function handle(): ?CompanySettings
    {
        return $this->repository->get();
    }
}
