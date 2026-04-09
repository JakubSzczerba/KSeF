<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Application\CreateContractor;

final readonly class CreateContractorCommand
{
    public function __construct(
        public string $name,
        public string $nip,
        public ?string $address = null,
        public ?string $email = null
    ) {}
}
