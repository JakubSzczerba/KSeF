<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Settings\Application\UpdateCompanySettings;

final readonly class UpdateCompanySettingsCommand
{
    public function __construct(
        public string $name,
        public string $nip,
        public ?string $address,
        public ?string $bankAccount,
        public ?string $vatNumber,
    ) {}
}
