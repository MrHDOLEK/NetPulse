<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain\Entity;

use App\Measurement\Domain\Entity\Measurement;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MeasurementShareTest extends TestCase
{
    private const string MEASUREMENT_ID = "11111111-1111-4111-8111-111111111111";
    private const string PROBE_ID = "22222222-2222-4222-8222-222222222222";
    private const string CONNECTION_ID = "33333333-3333-4333-8333-333333333333";

    public function testShareLazilyMintsA43CharTokenAndIsIdempotent(): void
    {
        $measurement = $this->measurement();

        self::assertNull($measurement->shareToken());

        $token = $measurement->share();

        self::assertMatchesRegularExpression("/^[A-Za-z0-9_-]{43}$/", $token);
        self::assertSame($token, $measurement->shareToken());

        self::assertSame($token, $measurement->share());
        self::assertSame($token, $measurement->shareToken());
    }

    private function measurement(): Measurement
    {
        return MeasurementMother::fromOoklaArray(
            [
                "type" => "result",
                "ping" => ["latency" => 12.5, "jitter" => 1.2],
                "download" => ["bandwidth" => 117_875_000, "bytes" => 1_200_000_000],
                "upload" => ["bandwidth" => 23_375_000, "bytes" => 240_000_000],
                "packetLoss" => 0.0,
                "isp" => "Orange Polska",
                "server" => ["id" => 12746, "host" => "speedtest.orange.pl", "port" => 8080, "name" => "Orange Polska", "location" => "Warsaw"],
            ],
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            true,
            new DateTimeImmutable("2026-06-06T10:00:00+00:00"),
        );
    }
}
