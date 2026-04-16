<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Frontend\Contractors;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;
use Ksef\Frontend\Contractors\Application\CreateContractor\CreateContractorCommand;
use Ksef\Frontend\Contractors\Application\CreateContractor\CreateContractorHandler;
use Ksef\Frontend\Contractors\Domain\Contractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateContractorHandlerTest extends TestCase
{
    #[Test]
    public function shouldCreateContractorWhenNipIsUnique(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);
        $saved = new Contractor(1, 'Firma ABC', '1234567890', null, null, '2026-04-14T00:00:00+00:00');

        $repository->expects(self::once())
            ->method('findByNip')
            ->with('1234567890')
            ->willReturn(null);

        $repository->expects(self::once())
            ->method('add')
            ->willReturn($saved);

        $handler = new CreateContractorHandler($repository);
        $result = $handler->handle(new CreateContractorCommand('Firma ABC', '1234567890'));

        self::assertSame(1, $result->id);
        self::assertSame('Firma ABC', $result->name);
        self::assertSame('1234567890', $result->nip);
    }

    #[Test]
    public function shouldThrowWhenNameIsEmpty(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);
        $repository->expects(self::never())->method('findByNip');

        $handler = new CreateContractorHandler($repository);

        $this->expectException(DomainValidationException::class);
        $this->expectExceptionMessageMatches('/Nazwa/');

        $handler->handle(new CreateContractorCommand('', '1234567890'));
    }

    #[Test]
    public function shouldThrowWhenNameIsWhitespaceOnly(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);
        $repository->expects(self::never())->method('findByNip');

        $handler = new CreateContractorHandler($repository);

        $this->expectException(DomainValidationException::class);

        $handler->handle(new CreateContractorCommand('   ', '1234567890'));
    }

    #[Test]
    public function shouldThrowWhenNipHasWrongLength(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);
        $repository->expects(self::never())->method('findByNip');

        $handler = new CreateContractorHandler($repository);

        $this->expectException(DomainValidationException::class);
        $this->expectExceptionMessageMatches('/NIP/');

        $handler->handle(new CreateContractorCommand('Firma ABC', '123456'));
    }

    #[Test]
    public function shouldThrowWhenNipContainsNonDigits(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);
        $repository->expects(self::never())->method('findByNip');

        $handler = new CreateContractorHandler($repository);

        $this->expectException(DomainValidationException::class);

        $handler->handle(new CreateContractorCommand('Firma ABC', '123-456-78-90'));
    }

    #[Test]
    public function shouldThrowWhenNipAlreadyExists(): void
    {
        $existing = new Contractor(5, 'Istniejąca Firma', '1234567890', null, null, '2026-01-01T00:00:00+00:00');
        $repository = $this->createMock(ContractorRepositoryInterface::class);

        $repository->expects(self::once())
            ->method('findByNip')
            ->with('1234567890')
            ->willReturn($existing);

        $repository->expects(self::never())->method('add');

        $handler = new CreateContractorHandler($repository);

        $this->expectException(DomainValidationException::class);
        $this->expectExceptionMessageMatches('/już istnieje/');

        $handler->handle(new CreateContractorCommand('Nowa Firma', '1234567890'));
    }
}
