<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Request\Validator;

use RuntimeException;

final class ValidationError extends RuntimeException
{
    public const GENERAL = "general";

    /**
     * @param array<mixed> $errors
     */
    public function __construct(
        /** @var array<mixed> */
        private array $errors,
    ) {
        parent::__construct("Request is invalid.", 400);
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
