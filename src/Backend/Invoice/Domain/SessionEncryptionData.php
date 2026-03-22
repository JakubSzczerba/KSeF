<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Domain;

final readonly class SessionEncryptionData
{
    public function __construct(
        public string $encryptedSymmetricKeyBase64,
        public string $initializationVectorBase64,
        public string $symmetricKeyRaw,
        public string $initializationVectorRaw
    ) {}
}
