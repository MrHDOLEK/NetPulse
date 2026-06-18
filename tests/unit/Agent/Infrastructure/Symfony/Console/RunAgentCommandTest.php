<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent\Infrastructure\Symfony\Console;

use App\Agent\Application\AgentTask;
use App\Agent\Application\AgentTick;
use App\Agent\Application\DuePlan;
use App\Agent\Application\SpeedtestOutcome;
use App\Agent\Infrastructure\Symfony\Console\RunAgentCommand;
use App\Tests\Unit\Agent\Application\FakeSpeedtestRunner;
use App\Tests\Unit\Agent\Application\RecordingNetPulseApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RunAgentCommandTest extends TestCase
{
    private const string CONN = '11111111-1111-7111-8111-111111111111';

    public function testOnceRunsASingleTickAndExitsSuccessfully(): void
    {
        $api = new RecordingNetPulseApiClient(new DuePlan([
            new AgentTask(self::CONN, '100'),
        ], 60));
        $runner = new FakeSpeedtestRunner([
            SpeedtestOutcome::success(['type' => 'result']),
        ]);

        $tick = new AgentTick($api, $runner, new NullLogger());
        $command = new RunAgentCommand($tick, new NullLogger(), 60);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--once' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $api->pushed);
        self::assertSame(self::CONN, $api->pushed[0]['connectionId']);
        self::assertTrue($api->pushed[0]['scheduled']);
        self::assertStringContainsString('1 task(s)', $tester->getDisplay());
    }
}
