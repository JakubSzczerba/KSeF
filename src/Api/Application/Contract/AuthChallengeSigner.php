<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Api\Application\Contract;

interface AuthChallengeSigner
{
    public function signChallenge(string $challenge): string;
}

