<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain;

use App\Measurement\Domain\Enum\MeasurementStatus;
use PHPUnit\Framework\TestCase;

final class MeasurementStatusTest extends TestCase
{
    public function testHasCompletedAndFailedCases(): void
    {
        $this->assertSame('completed', MeasurementStatus::Completed->value);
        $this->assertSame('failed', MeasurementStatus::Failed->value);
    }
}
