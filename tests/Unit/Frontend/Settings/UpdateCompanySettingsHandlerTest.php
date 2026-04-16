<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Frontend\Settings;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Settings\Application\Contract\CompanySettingsRepositoryInterface;
use Ksef\Frontend\Settings\Application\UpdateCompanySettings\UpdateCompanySettingsCommand;
use Ksef\Frontend\Settings\Application\UpdateCompanySettings\UpdateCompanySettingsHandler;
use Ksef\Frontend\Settings\Domain\CompanySettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateCompanySettingsHandlerTest extends TestCase
{
    #[Test]
    public function shouldSaveAndReturnSettingsWhenValid(): void
    {
        $saved = new CompanySettings(1, 'Firma XYZ', '1234567890', 'ul. Główna 1', 'PL12345678901234567890123456', null);
        $repository = $this->createMock(CompanySettingsRepositoryInterface::class);

        $repository->expects(self::once())
            ->method('save')
            ->willReturn($saved);

        $handler = new UpdateCompanySettingsHandler($repository);
        $result = $handler->handle(new UpdateCompanySettingsCommand(
            'Firma XYZ',
            '1234567890',
            'ul. Główna 1',
            'PL12345678901234567890123456',
            null,
        ));

        self::assertSame('Firma XYZ', $result->name);
        self::assertSame('1234567890', $result->nip);
    }

    #[Test]
    public function shouldThrowWhenNameIsEmpty(): void
    {
        $repository = $this->createMock(CompanySettingsRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        $handler = new UpdateCompanySettingsHandler($repository);

        $this->expectException(DomainValidationException::class);
        $this->expectExceptionMessageMatches('/Nazwa firmy/');

        $handler->handle(new UpdateCompanySettingsCommand('', '1234567890', null, null, null));
    }

    #[Test]
    public function shouldThrowWhenNameIsWhitespaceOnly(): void
    {
        $repository = $this->createMock(CompanySettingsRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        $handler = new UpdateCompanySettingsHandler($repository);

        $this->expectException(DomainValidationException::class);

        $handler->handle(new UpdateCompanySettingsCommand('   ', '1234567890', null, null, null));
    }

    #[Test]
    public function shouldThrowWhenNipHasWrongLength(): void
    {
        $repository = $this->createMock(CompanySettingsRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        $handler = new UpdateCompanySettingsHandler($repository);

        $this->expectException(DomainValidationException::class);
        $this->expectExceptionMessageMatches('/NIP/');

        $handler->handle(new UpdateCompanySettingsCommand('Firma', '123', null, null, null));
    }

    #[Test]
    public function shouldThrowWhenNipContainsNonDigits(): void
    {
        $repository = $this->createMock(CompanySettingsRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        $handler = new UpdateCompanySettingsHandler($repository);

        $this->expectException(DomainValidationException::class);

        $handler->handle(new UpdateCompanySettingsCommand('Firma', 'PL1234567890', null, null, null));
    }

    #[Test]
    public function shouldPassOptionalFieldsToRepository(): void
    {
        $repository = $this->createMock(CompanySettingsRepositoryInterface::class);

        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (CompanySettings $s): bool {
                return $s->bankAccount === 'PL12345678901234567890123456'
                    && $s->vatNumber === 'PL1234567890';
            }))
            ->willReturn(new CompanySettings(1, 'Firma', '1234567890', null, 'PL12345678901234567890123456', 'PL1234567890'));

        $handler = new UpdateCompanySettingsHandler($repository);
        $handler->handle(new UpdateCompanySettingsCommand(
            'Firma',
            '1234567890',
            null,
            'PL12345678901234567890123456',
            'PL1234567890',
        ));
    }
}
