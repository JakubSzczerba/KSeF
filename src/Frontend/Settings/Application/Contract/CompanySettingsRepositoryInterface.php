<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Settings\Application\Contract;

use Ksef\Frontend\Settings\Domain\CompanySettings;

interface CompanySettingsRepositoryInterface
{
    public function get(): ?CompanySettings;

    public function save(CompanySettings $settings): CompanySettings;
}
