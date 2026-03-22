<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Application;

use Ksef\Backend\Authentication\Application\Contract\AccessTokenStoreInterface;
use Ksef\Backend\Authentication\Domain\ValueObject\AccessToken;

final class AccessTokenStore implements AccessTokenStoreInterface
{
    private ?AccessToken $accessToken = null;

    public function get(): ?AccessToken
    {
        return $this->accessToken;
    }

    public function set(AccessToken $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function clear(): void
    {
        $this->accessToken = null;
    }
}
