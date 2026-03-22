<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Application\Contract;

use Ksef\Backend\Invoice\Domain\EncryptedInvoice;
use Ksef\Backend\Invoice\Domain\SessionEncryptionData;

interface InvoiceEncryptor
{
    public function createSessionEncryptionData(): SessionEncryptionData;

    public function encryptInvoice(string $invoiceXml, SessionEncryptionData $sessionEncryptionData): EncryptedInvoice;
}
