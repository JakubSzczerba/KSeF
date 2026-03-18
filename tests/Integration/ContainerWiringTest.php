<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Tests\Integration;

use Ksef\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContainerWiringTest extends TestCase
{
    #[Test]
    public function shouldBuildAllServicesWithoutErrors(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        self::assertNotNull($kernel->getContainer());

        $kernel->shutdown();
    }
}
