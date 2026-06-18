<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\ValueObject;

use App\Metrics\Domain\RemoteWrite\Exception\InvalidLabel;

final readonly class Label
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
        if ($name === '') {
            throw InvalidLabel::emptyName();
        }
    }
}
