<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Domain\ValueObject;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;

final readonly class KsefNip
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^\d{10}$/', $this->value)) {
            throw DomainValidationException::invalid('NIP', 'musi składać się z 10 cyfr');
        }
    }
}
