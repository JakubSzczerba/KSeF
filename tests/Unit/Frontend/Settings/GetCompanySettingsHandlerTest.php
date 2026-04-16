<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Frontend\Settings;

use Ksef\Frontend\Settings\Application\Contract\CompanySettingsRepositoryInterface;
use Ksef\Frontend\Settings\Application\GetCompanySettings\GetCompanySettingsHandler;
use Ksef\Frontend\Settings\Domain\CompanySettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetCompanySettingsHandlerTest extends TestCase
{
    #[Test]
    public function shouldReturnSettingsWhenStored(): void
    {
        $settings = new CompanySettings(1, 'Firma XYZ', '1234567890', 'ul. Główna 1', 'PL12345678901234567890123456', 'PL1234567890');
        $repository = $this->createMock(CompanySettingsRepositoryInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->willReturn($settings);

        $handler = new GetCompanySettingsHandler($repository);
        $result = $handler->handle();

        self::assertNotNull($result);
        self::assertSame('Firma XYZ', $result->name);
        self::assertSame('1234567890', $result->nip);
    }

    #[Test]
    public function shouldReturnNullWhenNoSettingsStored(): void
    {
        $repository = $this->createMock(CompanySettingsRepositoryInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->willReturn(null);

        $handler = new GetCompanySettingsHandler($repository);
        $result = $handler->handle();

        self::assertNull($result);
    }
}
