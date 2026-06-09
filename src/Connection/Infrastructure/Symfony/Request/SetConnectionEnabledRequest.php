<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Symfony\Request;

use App\Shared\Infrastructure\Utils\Request\RequestInterface;

final class SetConnectionEnabledRequest implements RequestInterface
{
    public string $probeId = "";
    public bool $enabled = false;
}
