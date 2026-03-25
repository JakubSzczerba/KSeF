<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Backend\Authentication;

use Ksef\Backend\Authentication\Application\AccessTokenStore;
use Ksef\Backend\Authentication\Application\AuthenticateHandler;
use Ksef\Backend\Authentication\Application\Contract\AuthChallengeSigner;
use Ksef\Backend\Shared\Application\KsefStatusPoller;
use Psr\Log\LoggerInterface;
use Ksef\Backend\Authentication\Domain\ValueObject\AccessToken;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthenticateHandlerTest extends TestCase
{
    #[Test]
    public function shouldAuthenticateAndStoreAccessToken(): void
    {
        $api = $this->createMock(KsefApi::class);
        $signer = $this->createMock(AuthChallengeSigner::class);
        $tokenStore = new AccessTokenStore();

        $api->expects(self::once())
            ->method('requestAuthChallenge')
            ->with('1234567890')
            ->willReturn(['challenge' => 'ch-1']);

        $signer->expects(self::once())
            ->method('signChallenge')
            ->with('ch-1')
            ->willReturn('<signed-xml/>');

        $api->expects(self::once())
            ->method('initXadesSignatureAuthentication')
            ->with('<signed-xml/>')
            ->willReturn([
                'referenceNumber' => 'auth-ref-1',
                'authenticationToken' => ['token' => 'auth-token-1'],
            ]);

        $api->expects(self::once())
            ->method('getAuthenticationStatus')
            ->with('auth-ref-1', 'auth-token-1')
            ->willReturn(['status' => ['code' => 200]]);

        $api->expects(self::once())
            ->method('redeemAuthenticationToken')
            ->with('auth-token-1')
            ->willReturn(['accessToken' => ['token' => 'access-token-1']]);

        $logger = $this->createStub(LoggerInterface::class);
        $handler = new AuthenticateHandler($api, $signer, $tokenStore, new KsefStatusPoller(0), $logger, '1234567890');

        $session = $handler->execute();

        self::assertSame('auth-ref-1', $session->referenceNumber->value);
        self::assertSame('auth-token-1', $session->authenticationToken->value);
        self::assertSame('access-token-1', $session->accessToken->value);
        self::assertEquals(new AccessToken('access-token-1'), $tokenStore->get());
    }
}
