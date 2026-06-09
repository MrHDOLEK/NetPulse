<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Utils\Request;

interface DeserializesRawBody extends RequestInterface
{
    /**
     * @return class-string
     */
    public static function rawBodyType(): string;

    /**
     * @param array<string,mixed> $payload the decoded JSON body, kept verbatim for storage
     */
    public function setRawBody(object $body, array $payload): void;
}
