<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Infrastructure\Store;

use Ksef\Backend\Authentication\Application\Contract\AccessTokenStoreInterface;
use Ksef\Backend\Authentication\Domain\ValueObject\AccessToken;
use Symfony\Component\HttpFoundation\RequestStack;

final class SessionAccessTokenStore implements AccessTokenStoreInterface
{
    private const SESSION_KEY = 'ksef.access_token';

    public function __construct(private readonly RequestStack $requestStack) {}

    public function get(): ?AccessToken
    {
        $session = $this->requestStack->getSession();
        $value = $session->get(self::SESSION_KEY);

        if (!is_string($value) || '' === $value) {
            return null;
        }

        return new AccessToken($value);
    }

    public function set(AccessToken $accessToken): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $accessToken->value);
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }
}
