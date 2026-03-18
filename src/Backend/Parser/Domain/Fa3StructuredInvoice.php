<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Parser\Domain;

final readonly class Fa3StructuredInvoice
{
    public function __construct(
        public string $xml,
        public string $rootElement,
        public ?string $namespaceUri,
        public string $formSystemCode,
        public string $formSchemaVersion,
        public string $formValue
    ) {}
}
