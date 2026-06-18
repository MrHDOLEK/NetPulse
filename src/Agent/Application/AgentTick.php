<?php

declare(strict_types=1);

namespace App\Agent\Application;

use Psr\Log\LoggerInterface;
use Throwable;

use function count;

final readonly class AgentTick
{
    public function __construct(
        private NetPulseApiClient $apiClient,
        private SpeedtestRunner $runner,
        private LoggerInterface $logger,
    ) {}

    public function run(): TickSummary
    {
        $plan = $this->apiClient->fetchDue();

        $this->logger->info('agent tick: due work fetched', ['tasks' => count($plan->tasks)]);

        $succeeded = 0;
        $failed = 0;
        $errored = 0;

        foreach ($plan->tasks as $task) {
            try {
                $this->logger->info('running speedtest', [
                    'connection' => $task->connectionId,
                    'server' => $task->serverId ?? 'auto',
                ]);

                $outcome = $this->runner->run($task->serverId);

                $this->apiClient->pushResult(
                    $task->connectionId,
                    $outcome->toOoklaJson($task->serverId),
                    scheduled: true,
                );

                if ($outcome->successful) {
                    $succeeded++;
                    $this->logger->info('result pushed', [
                        'connection' => $task->connectionId,
                        'status' => 'ok',
                    ]);
                } else {
                    $failed++;
                    $this->logger->info('result pushed', [
                        'connection' => $task->connectionId,
                        'status' => 'failed',
                    ]);
                    $this->logger->warning('task failed', [
                        'connection' => $task->connectionId,
                        'error' => $outcome->errorMessage ?? 'unknown',
                    ]);
                }
            } catch (Throwable $exception) {
                $errored++;
                $this->logger->warning('task failed', [
                    'connection' => $task->connectionId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return new TickSummary(
            tasks: count($plan->tasks),
            succeeded: $succeeded,
            failed: $failed,
            errored: $errored,
            pollAfterSeconds: $plan->pollAfterSeconds,
        );
    }
}
