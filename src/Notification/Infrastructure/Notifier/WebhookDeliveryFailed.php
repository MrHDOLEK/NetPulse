<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Notifier;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class WebhookDeliveryFailed extends RuntimeException
{
    public function __construct(int $status)
    {
        parent::__construct(
            "webhook delivery failed with status {$status}",
            $status >= Response::HTTP_OK ? $status : Response::HTTP_BAD_GATEWAY,
        );
    }
}
