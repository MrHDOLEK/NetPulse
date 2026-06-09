<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Response;

use App\Scheduling\Application\DueWork;
use App\Scheduling\Domain\ValueObject\DueTask;

use function array_map;

final readonly class DueWorkResponse
{
    public function __construct(
        private DueWork $dueWork,
    ) {}

    public static function fromDueWork(DueWork $dueWork): self
    {
        return new self($dueWork);
    }

    /**
     * @return array{tasks: list<array{connectionId: string, serverId: string|null}>, pollAfterSeconds: int}
     */
    public function toArray(): array
    {
        return [
            "tasks" => array_map(
                static fn(DueTask $task): array => [
                    "connectionId" => $task->connectionId->toString(),
                    "serverId" => $task->serverId,
                ],
                $this->dueWork->tasks->toArray(),
            ),
            "pollAfterSeconds" => $this->dueWork->pollAfterSeconds,
        ];
    }
}
