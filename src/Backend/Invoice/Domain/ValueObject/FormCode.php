<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Domain\ValueObject;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;

final readonly class FormCode
{
    public function __construct(
        public string $systemCode,
        public string $schemaVersion,
        public string $value
    ) {
        if ($this->systemCode === '' || $this->schemaVersion === '' || $this->value === '') {
            throw DomainValidationException::invalid('FormCode', 'wszystkie pola muszą być niepuste');
        }
    }
}
