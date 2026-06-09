<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain\ValueObject;

use App\Measurement\Domain\ValueObject\Latency;
use PHPUnit\Framework\TestCase;

final class LatencyTest extends TestCase
{
    public function testExposesAllFieldsInMilliseconds(): void
    {
        $latency = new Latency(
            ping: 12.5,
            pingLow: 11.0,
            pingHigh: 14.0,
            jitter: 1.2,
            downloadJitter: 2.5,
            uploadJitter: 3.1,
            downloadLatencyIqm: 18.4,
            downloadLatencyLow: 13.0,
            downloadLatencyHigh: 40.0,
            uploadLatencyIqm: 22.1,
            uploadLatencyLow: 15.0,
            uploadLatencyHigh: 55.0,
        );

        $this->assertSame(12.5, $latency->ping);
        $this->assertSame(11.0, $latency->pingLow);
        $this->assertSame(14.0, $latency->pingHigh);
        $this->assertSame(1.2, $latency->jitter);
        $this->assertSame(2.5, $latency->downloadJitter);
        $this->assertSame(3.1, $latency->uploadJitter);
        $this->assertSame(18.4, $latency->downloadLatencyIqm);
        $this->assertSame(13.0, $latency->downloadLatencyLow);
        $this->assertSame(40.0, $latency->downloadLatencyHigh);
        $this->assertSame(22.1, $latency->uploadLatencyIqm);
        $this->assertSame(15.0, $latency->uploadLatencyLow);
        $this->assertSame(55.0, $latency->uploadLatencyHigh);
    }
}
