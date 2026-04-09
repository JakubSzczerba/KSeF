<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Application\UpdateContractor;

final readonly class UpdateContractorCommand
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $address = null,
        public ?string $email = null
    ) {}
}
