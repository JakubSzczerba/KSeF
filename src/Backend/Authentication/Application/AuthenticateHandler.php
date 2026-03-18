<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Authentication\Application;

use Ksef\Backend\Authentication\Application\Contract\AuthChallengeSigner;
use Ksef\Backend\Authentication\Domain\AuthenticationSession;
use Ksef\Backend\Authentication\Domain\ValueObject\AccessToken;
use Ksef\Backend\Authentication\Domain\ValueObject\AuthenticationReferenceNumber;
use Ksef\Backend\Authentication\Domain\ValueObject\AuthenticationToken;
use Ksef\Backend\Authentication\Domain\ValueObject\KsefNip;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use Ksef\Backend\Authentication\Application\Contract\AccessTokenStoreInterface;
use Ksef\Backend\Authentication\Application\Contract\AuthenticateHandlerInterface;
use Ksef\Backend\Shared\Application\Exception\AuthenticationFailedException;
use Ksef\Backend\Shared\Application\Exception\IntegrationResponseException;
use Ksef\Backend\Shared\Application\KsefStatusPoller;

final class AuthenticateHandler implements AuthenticateHandlerInterface
{
    private const SUCCESS_CODE = 200;
    private const MAX_ATTEMPTS = 30;

    /**
     * @var list<int>
     */
    private const AUTH_TERMINAL_CODES = [200, 400, 401, 403, 500];

    public function __construct(
        private readonly KsefApi $ksefApi,
        private readonly AuthChallengeSigner $authChallengeSigner,
        private readonly AccessTokenStoreInterface $accessTokenStore,
        private readonly KsefStatusPoller $statusPoller,
        private readonly string $ksefNip
    ) {}

    public function execute(): AuthenticationSession
    {
        $nip = new KsefNip($this->ksefNip);
        $challengeData = $this->ksefApi->requestAuthChallenge($nip->value);
        $challenge = (string) ($challengeData['challenge'] ?? '');
        if ($challenge === '') {
            throw new IntegrationResponseException('Brak challenge w odpowiedzi API KSeF.');
        }

        $signedXml = $this->authChallengeSigner->signChallenge($challenge);
        $initAuthData = $this->ksefApi->initXadesSignatureAuthentication($signedXml);

        $referenceNumber = (string) ($initAuthData['referenceNumber'] ?? '');
        $authenticationToken = (string) ($initAuthData['authenticationToken']['token'] ?? '');
        if ($referenceNumber === '' || $authenticationToken === '') {
            throw new IntegrationResponseException('Brak referenceNumber lub authenticationToken w odpowiedzi /auth/xades-signature.');
        }

        $authenticationReferenceNumber = new AuthenticationReferenceNumber($referenceNumber);
        $sessionToken = new AuthenticationToken($authenticationToken);
        $this->waitForAuthentication($authenticationReferenceNumber, $sessionToken);

        $tokens = $this->ksefApi->redeemAuthenticationToken($sessionToken->value);
        $accessToken = (string) ($tokens['accessToken']['token'] ?? '');
        if ($accessToken === '') {
            throw new IntegrationResponseException('Brak access tokenu w odpowiedzi /auth/token/redeem.');
        }

        $authenticationAccessToken = new AccessToken($accessToken);
        $this->accessTokenStore->set($authenticationAccessToken);

        return new AuthenticationSession($authenticationReferenceNumber, $sessionToken, $authenticationAccessToken);
    }

    private function waitForAuthentication(
        AuthenticationReferenceNumber $referenceNumber,
        AuthenticationToken $authenticationToken
    ): void {
        $statusData = $this->statusPoller->pollUntilTerminal(
            fn (): array => $this->ksefApi->getAuthenticationStatus($referenceNumber->value, $authenticationToken->value),
            self::AUTH_TERMINAL_CODES,
            self::MAX_ATTEMPTS,
            'Timeout podczas uwierzytelnienia.'
        );

        $statusCode = (int) ($statusData['status']['code'] ?? 0);
        if (self::SUCCESS_CODE !== $statusCode) {
            throw new AuthenticationFailedException(
                'Uwierzytelnienie nie powiodło się: ' .
                ((string) ($statusData['status']['description'] ?? 'nieznany błąd'))
            );
        }
    }
}
