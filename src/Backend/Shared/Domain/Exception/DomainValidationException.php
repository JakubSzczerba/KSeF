<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Shared\Domain\Exception;

final class DomainValidationException extends DomainException
{
    public static function empty(string $field): self
    {
        return new self(sprintf('%s nie może być puste.', $field));
    }

    public static function invalid(string $field, string $reason): self
    {
        return new self(sprintf('%s jest niepoprawne: %s', $field, $reason));
    }
}
