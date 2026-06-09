<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

final readonly class ExpectedRow
{
    public function __construct(
        public string $connectionId,
        public string $connectionName,
        public string $probeName,
        public int $expectedDownloadBits,
        public int $expectedUploadBits,
    ) {}
}
