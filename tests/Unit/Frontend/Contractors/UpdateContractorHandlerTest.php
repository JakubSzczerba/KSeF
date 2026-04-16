<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Frontend\Contractors;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;
use Ksef\Frontend\Contractors\Application\UpdateContractor\UpdateContractorCommand;
use Ksef\Frontend\Contractors\Application\UpdateContractor\UpdateContractorHandler;
use Ksef\Frontend\Contractors\Domain\Contractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateContractorHandlerTest extends TestCase
{
    #[Test]
    public function shouldUpdateContractorWhenFound(): void
    {
        $existing = new Contractor(3, 'Stara Nazwa', '1234567890', null, null, '2026-01-01T00:00:00+00:00');
        $repository = $this->createMock(ContractorRepositoryInterface::class);

        $repository->expects(self::once())
            ->method('findById')
            ->with(3)
            ->willReturn($existing);

        $repository->expects(self::once())
            ->method('update')
            ->willReturn(true);

        $handler = new UpdateContractorHandler($repository);
        $result = $handler->handle(new UpdateContractorCommand(3, 'Nowa Nazwa', 'ul. Nowa 1'));

        self::assertTrue($result);
    }

    #[Test]
    public function shouldReturnFalseWhenContractorNotFound(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);

        $repository->expects(self::once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        $repository->expects(self::never())->method('update');

        $handler = new UpdateContractorHandler($repository);
        $result = $handler->handle(new UpdateContractorCommand(99, 'Jakaś Firma'));

        self::assertFalse($result);
    }

    #[Test]
    public function shouldThrowWhenNameIsEmpty(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');

        $handler = new UpdateContractorHandler($repository);

        $this->expectException(DomainValidationException::class);
        $this->expectExceptionMessageMatches('/Nazwa/');

        $handler->handle(new UpdateContractorCommand(1, ''));
    }

    #[Test]
    public function shouldPreserveNipFromExistingContractor(): void
    {
        $existing = new Contractor(7, 'Stara Nazwa', '9876543210', 'Stary adres', 'old@test.pl', '2026-01-01T00:00:00+00:00');
        $repository = $this->createMock(ContractorRepositoryInterface::class);

        $repository->method('findById')->willReturn($existing);

        $repository->expects(self::once())
            ->method('update')
            ->with(self::callback(static function (Contractor $c): bool {
                return $c->nip === '9876543210';
            }))
            ->willReturn(true);

        $handler = new UpdateContractorHandler($repository);
        $handler->handle(new UpdateContractorCommand(7, 'Nowa Nazwa', null, 'new@test.pl'));
    }
}
