<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Infrastructure\Config;

use App\Metrics\Infrastructure\Config\PrometheusConfig;
use PHPUnit\Framework\TestCase;

final class PrometheusConfigTest extends TestCase
{
    public function testParsesEnabledFlagAndFreshnessWindow(): void
    {
        $config = new PrometheusConfig(metricsEnabled: true, allowedIpsRaw: '', freshnessWindowSeconds: 3600);

        self::assertTrue($config->metricsEnabled());
        self::assertSame(3600, $config->freshnessWindowSeconds());
        self::assertSame([], $config->allowedCidrs());
    }

    public function testParsesCommaSeparatedCidrListTrimmingWhitespace(): void
    {
        $config = new PrometheusConfig(
            metricsEnabled: false,
            allowedIpsRaw: '10.0.0.0/8, 192.168.0.0/16 ,127.0.0.1/32',
            freshnessWindowSeconds: 60,
        );

        self::assertFalse($config->metricsEnabled());
        self::assertSame(['10.0.0.0/8', '192.168.0.0/16', '127.0.0.1/32'], $config->allowedCidrs());
    }

    public function testEmptyAllowlistMeansAllIpsAllowed(): void
    {
        $config = new PrometheusConfig(metricsEnabled: true, allowedIpsRaw: '   ', freshnessWindowSeconds: 3600);

        self::assertSame([], $config->allowedCidrs());
    }
}
