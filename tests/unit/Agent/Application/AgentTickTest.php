<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent\Application;

use App\Agent\Application\AgentTask;
use App\Agent\Application\AgentTick;
use App\Agent\Application\DuePlan;
use App\Agent\Application\SpeedtestOutcome;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AgentTickTest extends TestCase
{
    private const string CONN_A = "11111111-1111-7111-8111-111111111111";
    private const string CONN_B = "22222222-2222-7222-8222-222222222222";
    private const string CONN_C = "33333333-3333-7333-8333-333333333333";

    public function testRunsEveryDueTaskAndPushesEachResultAsScheduled(): void
    {
        $plan = new DuePlan([
            new AgentTask(self::CONN_A, "100"),
            new AgentTask(self::CONN_B, null),
        ], 60);

        $api = new RecordingNetPulseApiClient($plan);
        $runner = new FakeSpeedtestRunner([
            SpeedtestOutcome::success(["type" => "result", "ping" => ["latency" => 9.0]]),
            SpeedtestOutcome::success(["type" => "result", "ping" => ["latency" => 5.0]]),
        ]);

        $summary = (new AgentTick($api, $runner, new NullLogger()))->run();

        self::assertSame(["100", null], $runner->runForServerIds);
        self::assertCount(2, $api->pushed);

        self::assertSame(self::CONN_A, $api->pushed[0]["connectionId"]);
        self::assertTrue($api->pushed[0]["scheduled"]);
        self::assertSame("result", $api->pushed[0]["ookla"]["type"]);

        self::assertSame(self::CONN_B, $api->pushed[1]["connectionId"]);
        self::assertTrue($api->pushed[1]["scheduled"]);

        self::assertSame(2, $summary->tasks);
        self::assertSame(2, $summary->succeeded);
        self::assertSame(0, $summary->failed);
        self::assertSame(0, $summary->errored);
        self::assertSame(60, $summary->pollAfterSeconds);
    }

    public function testFailedRunStillPushesAServerAcceptedFailedPayload(): void
    {
        $plan = new DuePlan([new AgentTask(self::CONN_A, "777")], 30);

        $api = new RecordingNetPulseApiClient($plan);
        $runner = new FakeSpeedtestRunner([SpeedtestOutcome::failure("no servers reachable")]);

        $summary = (new AgentTick($api, $runner, new NullLogger()))->run();

        self::assertCount(1, $api->pushed);
        $payload = $api->pushed[0]["ookla"];

        self::assertArrayHasKey("type", $payload);
        self::assertNotSame("result", $payload["type"]);
        self::assertSame("error", $payload["type"]);

        self::assertSame(["id" => "777"], $payload["server"]);
        self::assertTrue($api->pushed[0]["scheduled"]);

        self::assertSame(1, $summary->failed);
        self::assertSame(0, $summary->succeeded);
    }

    public function testThrowingTaskIsCaughtAndOtherTasksStillRun(): void
    {
        $plan = new DuePlan([
            new AgentTask(self::CONN_A, null),
            new AgentTask(self::CONN_B, null),
            new AgentTask(self::CONN_C, null),
        ], 60);

        $api = new RecordingNetPulseApiClient($plan);
        $runner = new FakeSpeedtestRunner([
            SpeedtestOutcome::success(["type" => "result"]),
            "throw",
            SpeedtestOutcome::success(["type" => "result"]),
        ]);

        $summary = (new AgentTick($api, $runner, new NullLogger()))->run();

        self::assertCount(2, $api->pushed);
        self::assertSame(self::CONN_A, $api->pushed[0]["connectionId"]);
        self::assertSame(self::CONN_C, $api->pushed[1]["connectionId"]);

        self::assertSame(3, $summary->tasks);
        self::assertSame(2, $summary->succeeded);
        self::assertSame(1, $summary->errored);
    }

    public function testEmptyPlanPushesNothing(): void
    {
        $api = new RecordingNetPulseApiClient(new DuePlan([], 60));
        $runner = new FakeSpeedtestRunner([]);

        $summary = (new AgentTick($api, $runner, new NullLogger()))->run();

        self::assertSame([], $api->pushed);
        self::assertSame(0, $summary->tasks);
    }
}
