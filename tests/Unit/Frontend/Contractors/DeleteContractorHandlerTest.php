<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Unit\Frontend\Contractors;

use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;
use Ksef\Frontend\Contractors\Application\DeleteContractor\DeleteContractorHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeleteContractorHandlerTest extends TestCase
{
    #[Test]
    public function shouldReturnTrueWhenContractorDeleted(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('delete')
            ->with(5)
            ->willReturn(true);

        $handler = new DeleteContractorHandler($repository);

        self::assertTrue($handler->handle(5));
    }

    #[Test]
    public function shouldReturnFalseWhenContractorNotFound(): void
    {
        $repository = $this->createMock(ContractorRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('delete')
            ->with(99)
            ->willReturn(false);

        $handler = new DeleteContractorHandler($repository);

        self::assertFalse($handler->handle(99));
    }
}
