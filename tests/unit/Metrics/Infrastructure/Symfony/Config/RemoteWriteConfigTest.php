<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Infrastructure\Symfony\Config;

use App\Metrics\Infrastructure\Symfony\Config\RemoteWriteConfig;
use PHPUnit\Framework\TestCase;

final class RemoteWriteConfigTest extends TestCase
{
    public function testParsesExtraLabelsCsvFromRawString(): void
    {
        $config = RemoteWriteConfig::fromRaw(
            enabled: true,
            url: 'https://x/api/v1/write',
            auth: 'bearer:tok',
            extraLabelsRaw: 'env=prod,region=eu',
        );

        self::assertTrue($config->enabled);
        self::assertSame('https://x/api/v1/write', $config->url);
        self::assertSame('bearer:tok', $config->auth);
        self::assertSame(['env' => 'prod', 'region' => 'eu'], $config->extraLabels);
    }

    public function testEmptyExtraLabelsYieldEmptyArray(): void
    {
        $config = RemoteWriteConfig::fromRaw(true, 'https://x', null, '');

        self::assertSame([], $config->extraLabels);
    }

    public function testNullAuthStaysNull(): void
    {
        $config = RemoteWriteConfig::fromRaw(false, '', '', '');

        self::assertNull($config->auth);
    }
}
