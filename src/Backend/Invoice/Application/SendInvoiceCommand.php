<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Application;

final readonly class SendInvoiceCommand
{
    public function __construct(
        public string $invoiceXml,
        public string $formSystemCode = 'FA (3)',
        public string $formSchemaVersion = '1-0E',
        public string $formValue = 'FA',
        public bool $offlineMode = false
    ) {}
}
