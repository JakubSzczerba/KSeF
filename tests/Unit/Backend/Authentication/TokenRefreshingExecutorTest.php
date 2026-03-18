<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Backend\Authentication;

use Ksef\Backend\Authentication\Application\AccessTokenStore;
use Ksef\Backend\Authentication\Application\Contract\AuthenticateHandlerInterface;
use Ksef\Backend\Authentication\Application\TokenRefreshingExecutor;
use Ksef\Backend\Authentication\Domain\AuthenticationSession;
use Ksef\Backend\Authentication\Domain\ValueObject\AccessToken;
use Ksef\Backend\Authentication\Domain\ValueObject\AuthenticationReferenceNumber;
use Ksef\Backend\Authentication\Domain\ValueObject\AuthenticationToken;
use Ksef\Backend\Shared\Infrastructure\Exception\ApiClientException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenRefreshingExecutorTest extends TestCase
{
    #[Test]
    public function shouldReturnResultOnFirstAttempt(): void
    {
        $tokenStore = new AccessTokenStore();
        $tokenStore->set(new AccessToken('token-1'));
        $authHandler = $this->createMock(AuthenticateHandlerInterface::class);
        $authHandler->expects(self::never())->method('execute');

        $executor = new TokenRefreshingExecutor($tokenStore, $authHandler);

        $result = $executor->executeWithRetry(
            fn (AccessToken $token): string => 'result-' . $token->value
        );

        self::assertSame('result-token-1', $result);
    }

    #[Test]
    public function shouldRefreshTokenAndRetryOn401(): void
    {
        $tokenStore = new AccessTokenStore();
        $tokenStore->set(new AccessToken('expired-token'));

        $authHandler = $this->createMock(AuthenticateHandlerInterface::class);
        $authHandler->expects(self::once())
            ->method('execute')
            ->willReturn(new AuthenticationSession(
                new AuthenticationReferenceNumber('ref-1'),
                new AuthenticationToken('auth-1'),
                new AccessToken('new-token')
            ));

        $executor = new TokenRefreshingExecutor($tokenStore, $authHandler);

        $callCount = 0;
        $result = $executor->executeWithRetry(
            function (AccessToken $token) use (&$callCount): string {
                $callCount++;
                if ($callCount === 1) {
                    throw new ApiClientException('Unauthorized', 401);
                }
                return 'result-' . $token->value;
            }
        );

        self::assertSame(2, $callCount);
        self::assertSame('result-new-token', $result);
        self::assertNull($tokenStore->get());
    }

    #[Test]
    public function shouldNotRetryOnNon401Exceptions(): void
    {
        $tokenStore = new AccessTokenStore();
        $tokenStore->set(new AccessToken('token-1'));
        $authHandler = $this->createMock(AuthenticateHandlerInterface::class);
        $authHandler->expects(self::never())->method('execute');

        $executor = new TokenRefreshingExecutor($tokenStore, $authHandler);

        $this->expectException(ApiClientException::class);
        $this->expectExceptionMessage('Server error');

        $executor->executeWithRetry(
            fn (AccessToken $token): never => throw new ApiClientException('Server error', 500)
        );
    }
}
