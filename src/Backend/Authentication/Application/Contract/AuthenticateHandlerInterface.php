<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Application\Contract;

use Ksef\Backend\Authentication\Domain\AuthenticationSession;

interface AuthenticateHandlerInterface
{
    public function execute(): AuthenticationSession;
}
