<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\Exception;

use RuntimeException;

final class RemoteWriteFailed extends RuntimeException
{
    public static function withStatus(int $status, string $body): self
    {
        return new self("Remote write endpoint responded with HTTP {$status}: {$body}");
    }

    public static function transport(string $reason): self
    {
        return new self("Remote write transport error: {$reason}");
    }
}
