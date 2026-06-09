<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain;

use App\Measurement\Domain\Exception\MeasurementNotFound;
use App\Shared\Domain\NotFoundException;
use PHPUnit\Framework\TestCase;

final class MeasurementNotFoundTest extends TestCase
{
    public function testIsANotFoundException(): void
    {
        $this->assertInstanceOf(NotFoundException::class, new MeasurementNotFound());
    }
}
