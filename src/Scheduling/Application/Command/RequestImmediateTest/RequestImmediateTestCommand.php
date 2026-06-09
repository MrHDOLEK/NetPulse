<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\RequestImmediateTest;

final readonly class RequestImmediateTestCommand
{
    public function __construct(
        public string $scope,
        public ?string $connectionId,
        public ?string $forcedServerId,
    ) {}
}
