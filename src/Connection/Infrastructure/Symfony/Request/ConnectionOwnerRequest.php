<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Symfony\Request;

use App\Shared\Infrastructure\Utils\Request\RequestInterface;

final class ConnectionOwnerRequest implements RequestInterface
{
    public string $probeId = '';
}
