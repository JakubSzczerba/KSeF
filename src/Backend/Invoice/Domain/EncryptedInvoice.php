<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Domain;

final readonly class EncryptedInvoice
{
    public function __construct(
        public string $invoiceHashBase64,
        public int $invoiceSize,
        public string $encryptedInvoiceHashBase64,
        public int $encryptedInvoiceSize,
        public string $encryptedInvoiceContentBase64
    ) {}
}
