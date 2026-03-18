<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Backend\Invoice;

use Ksef\Backend\Authentication\Application\AccessTokenStore;
use Ksef\Backend\Authentication\Application\Contract\AuthenticateHandlerInterface;
use Ksef\Backend\Authentication\Application\TokenRefreshingExecutor;
use Ksef\Backend\Authentication\Domain\AuthenticationSession;
use Ksef\Backend\Authentication\Domain\ValueObject\AccessToken;
use Ksef\Backend\Authentication\Domain\ValueObject\AuthenticationReferenceNumber;
use Ksef\Backend\Authentication\Domain\ValueObject\AuthenticationToken;
use Ksef\Backend\Invoice\Application\Contract\InvoiceEncryptor;
use Ksef\Backend\Invoice\Application\SendInvoiceCommand;
use Ksef\Backend\Invoice\Application\SendInvoiceHandler;
use Ksef\Backend\Invoice\Domain\EncryptedInvoice;
use Ksef\Backend\Invoice\Domain\SessionEncryptionData;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use Ksef\Backend\Shared\Application\Exception\IntegrationResponseException;
use Ksef\Backend\Shared\Application\KsefStatusPoller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SendInvoiceHandlerTest extends TestCase
{
    #[Test]
    public function shouldThrowDetailedExceptionWhenInvoiceStatusIsSemanticError(): void
    {
        $api = $this->createMock(KsefApi::class);
        $encryptor = $this->createMock(InvoiceEncryptor::class);
        $authHandler = $this->createMock(AuthenticateHandlerInterface::class);

        $tokenStore = new AccessTokenStore();
        $tokenStore->set(new AccessToken('access-token-1'));
        $tokenRefreshingExecutor = new TokenRefreshingExecutor($tokenStore, $authHandler);

        $sessionEncryptionData = new SessionEncryptionData('enc-key', 'iv-b64', 'key-raw', 'iv-raw');
        $encryptedInvoice = new EncryptedInvoice('h1', 10, 'h2', 12, 'content-b64');

        $encryptor->expects(self::once())->method('createSessionEncryptionData')->willReturn($sessionEncryptionData);
        $encryptor->expects(self::once())->method('encryptInvoice')->willReturn($encryptedInvoice);

        $api->expects(self::once())->method('openOnlineSession')->willReturn(['referenceNumber' => 'session-1']);
        $api->expects(self::once())->method('sendInvoice')->willReturn(['referenceNumber' => 'invoice-1']);
        $api->expects(self::once())->method('closeOnlineSession');
        $api->expects(self::once())->method('getSessionInvoiceStatus')->willReturn([
            'status' => [
                'code' => 450,
                'description' => 'Błąd weryfikacji semantyki dokumentu faktury',
                'details' => ['Pole P_1 ma nieprawidłowy format.'],
            ],
        ]);
        $api->expects(self::never())->method('getSessionStatus');

        $handler = new SendInvoiceHandler($api, $encryptor, $tokenRefreshingExecutor, new KsefStatusPoller(0));

        $this->expectException(IntegrationResponseException::class);
        $this->expectExceptionMessageMatches('/450.*Pole P_1 ma nieprawidłowy format\./');

        $handler->execute(new SendInvoiceCommand('<xml/>'));
    }

    #[Test]
    public function shouldSendInvoiceUsingExistingAccessToken(): void
    {
        $api = $this->createMock(KsefApi::class);
        $encryptor = $this->createMock(InvoiceEncryptor::class);
        $authHandler = $this->createMock(AuthenticateHandlerInterface::class);

        $tokenStore = new AccessTokenStore();
        $tokenStore->set(new AccessToken('access-token-1'));
        $tokenRefreshingExecutor = new TokenRefreshingExecutor($tokenStore, $authHandler);

        $sessionEncryptionData = new SessionEncryptionData('enc-key', 'iv-b64', 'key-raw', 'iv-raw');
        $encryptedInvoice = new EncryptedInvoice('h1', 10, 'h2', 12, 'content-b64');

        $encryptor->expects(self::once())->method('createSessionEncryptionData')->willReturn($sessionEncryptionData);
        $encryptor->expects(self::once())->method('encryptInvoice')->with('<xml/>', $sessionEncryptionData)->willReturn($encryptedInvoice);

        $api->expects(self::once())->method('openOnlineSession')->with('access-token-1', self::isArray())->willReturn(['referenceNumber' => 'session-1']);
        $api->expects(self::once())->method('sendInvoice')->with('access-token-1', 'session-1', self::isArray())->willReturn(['referenceNumber' => 'invoice-1']);
        $api->expects(self::once())->method('closeOnlineSession')->with('access-token-1', 'session-1');
        $api->expects(self::once())->method('getSessionInvoiceStatus')->with('access-token-1', 'session-1', 'invoice-1')->willReturn(['status' => ['code' => 200]]);
        $api->expects(self::once())->method('getSessionStatus')->with('access-token-1', 'session-1')->willReturn(['status' => ['code' => 200]]);

        $authHandler->expects(self::never())->method('execute');

        $handler = new SendInvoiceHandler($api, $encryptor, $tokenRefreshingExecutor, new KsefStatusPoller(0));

        $result = $handler->execute(new SendInvoiceCommand('<xml/>'));

        self::assertSame('session-1', $result->sessionReferenceNumber->value);
        self::assertSame('invoice-1', $result->invoiceReferenceNumber->value);
        self::assertNull($result->closeSessionError);
    }

    #[Test]
    public function shouldAuthenticateWhenAccessTokenMissing(): void
    {
        $api = $this->createMock(KsefApi::class);
        $encryptor = $this->createMock(InvoiceEncryptor::class);
        $authHandler = $this->createMock(AuthenticateHandlerInterface::class);

        $tokenStore = new AccessTokenStore();
        $authHandler->expects(self::once())
            ->method('execute')
            ->willReturn(new AuthenticationSession(
                new AuthenticationReferenceNumber('ref-1'),
                new AuthenticationToken('auth-token-1'),
                new AccessToken('new-access-token')
            ));
        $tokenRefreshingExecutor = new TokenRefreshingExecutor($tokenStore, $authHandler);

        $sessionEncryptionData = new SessionEncryptionData('enc-key', 'iv-b64', 'key-raw', 'iv-raw');
        $encryptedInvoice = new EncryptedInvoice('h1', 10, 'h2', 12, 'content-b64');

        $encryptor->expects(self::once())->method('createSessionEncryptionData')->willReturn($sessionEncryptionData);
        $encryptor->expects(self::once())->method('encryptInvoice')->willReturn($encryptedInvoice);

        $api->expects(self::once())->method('openOnlineSession')->with('new-access-token', self::isArray())->willReturn(['referenceNumber' => 'session-1']);
        $api->expects(self::once())->method('sendInvoice')->with('new-access-token', 'session-1', self::isArray())->willReturn(['referenceNumber' => 'invoice-1']);
        $api->expects(self::once())->method('closeOnlineSession')->with('new-access-token', 'session-1');
        $api->expects(self::once())->method('getSessionInvoiceStatus')->willReturn(['status' => ['code' => 200]]);
        $api->expects(self::once())->method('getSessionStatus')->willReturn(['status' => ['code' => 200]]);

        $handler = new SendInvoiceHandler($api, $encryptor, $tokenRefreshingExecutor, new KsefStatusPoller(0));

        $handler->execute(new SendInvoiceCommand('<xml/>'));
    }
}
