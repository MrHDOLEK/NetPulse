<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine;

use App\Auth\Infrastructure\Doctrine\Type\TotpSecretType;
use App\Auth\Infrastructure\Security\TotpSecretEncryptor;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

final readonly class RegisterTotpSecretTypeMiddleware implements Middleware
{
    public function __construct(
        private TotpSecretEncryptor $encryptor,
    ) {}

    public function wrap(Driver $driver): Driver
    {
        TotpSecretType::setEncryptor($this->encryptor);

        return $driver;
    }
}
