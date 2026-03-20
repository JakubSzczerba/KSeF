<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Domain;

use Ksef\Backend\Authentication\Domain\ValueObject\AccessToken;
use Ksef\Backend\Authentication\Domain\ValueObject\AuthenticationReferenceNumber;
use Ksef\Backend\Authentication\Domain\ValueObject\AuthenticationToken;

final readonly class AuthenticationSession
{
    public function __construct(
        public AuthenticationReferenceNumber $referenceNumber,
        public AuthenticationToken $authenticationToken,
        public AccessToken $accessToken
    ) {}
}
