<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Application;

use Ksef\Backend\Authentication\Application\Contract\AccessTokenStoreInterface;
use Ksef\Backend\Authentication\Application\Contract\AuthenticateHandlerInterface;
use Ksef\Backend\Authentication\Domain\ValueObject\AccessToken;
use Ksef\Backend\Shared\Infrastructure\Exception\ApiClientException;

final class TokenRefreshingExecutor
{
    /**
     * @var list<int>
     */
    private const REFRESH_ON_HTTP_CODES = [401, 403];

    public function __construct(
        private readonly AccessTokenStoreInterface $accessTokenStore,
        private readonly AuthenticateHandlerInterface $authenticateHandler,
    ) {}

    public function getValidToken(): AccessToken
    {
        $stored = $this->accessTokenStore->get();
        if (null !== $stored) {
            return $stored;
        }

        return $this->authenticateHandler->execute()->accessToken;
    }

    /**
     * Executes an operation with the current access token.
     * On 401/403: clears the store, re-authenticates, retries once.
     *
     * @template T
     * @param callable(AccessToken): T $operation
     * @return T
     */
    public function executeWithRetry(callable $operation): mixed
    {
        $token = $this->getValidToken();

        try {
            return $operation($token);
        } catch (ApiClientException $e) {
            if (!in_array($e->getHttpStatusCode(), self::REFRESH_ON_HTTP_CODES, true)) {
                throw $e;
            }

            $this->accessTokenStore->clear();
            $newToken = $this->authenticateHandler->execute()->accessToken;

            return $operation($newToken);
        }
    }
}
