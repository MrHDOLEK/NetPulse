<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Domain;

use App\Probe\Domain\Exception\InvalidProbeToken;
use App\Probe\Domain\Exception\ProbeDisabled;
use App\Shared\Domain\DomainException;
use PHPUnit\Framework\TestCase;

final class ProbeAuthExceptionsTest extends TestCase
{
    public function testInvalidProbeTokenIsNoArgDomainException(): void
    {
        $exception = new InvalidProbeToken();

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    public function testProbeDisabledIsNoArgDomainException(): void
    {
        $exception = new ProbeDisabled();

        $this->assertInstanceOf(DomainException::class, $exception);
    }
}
