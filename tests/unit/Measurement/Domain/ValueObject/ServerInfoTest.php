<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain\ValueObject;

use App\Measurement\Domain\ValueObject\ServerInfo;
use PHPUnit\Framework\TestCase;

final class ServerInfoTest extends TestCase
{
    public function testExposesAllFields(): void
    {
        $server = new ServerInfo(
            serverId: '12746',
            serverName: 'Orange Polska',
            serverLocation: 'Warsaw',
            serverHost: 'speedtest.orange.pl:8080',
            isp: 'Orange Polska',
        );

        $this->assertSame('12746', $server->serverId);
        $this->assertSame('Orange Polska', $server->serverName);
        $this->assertSame('Warsaw', $server->serverLocation);
        $this->assertSame('speedtest.orange.pl:8080', $server->serverHost);
        $this->assertSame('Orange Polska', $server->isp);
    }
}
