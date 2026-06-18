<?php

declare(strict_types=1);

namespace App\Measurement\Infrastructure\Symfony\Request;

use App\Measurement\Application\Ookla\OoklaResult;
use App\Shared\Infrastructure\Utils\Request\DeserializesRawBody;
use Symfony\Component\Validator\Constraints as Assert;

final class RecordMeasurementRequest implements DeserializesRawBody
{
    #[Assert\NotBlank(message: 'VALIDATION.CONNECTION_ID_REQUIRED')]
    #[Assert\Uuid(message: 'VALIDATION.CONNECTION_ID_INVALID')]
    public string $connectionId = '';

    public bool $scheduled = false;
    public OoklaResult $ookla;

    /** @var array<string,mixed> */
    public array $raw = [];

    public function __construct()
    {
        $this->ookla = new OoklaResult();
    }

    public static function rawBodyType(): string
    {
        return OoklaResult::class;
    }

    public function setRawBody(object $body, array $payload): void
    {
        if ($body instanceof OoklaResult) {
            $this->ookla = $body;
        }

        $this->raw = $payload;
    }

    #[Assert\NotBlank(message: 'VALIDATION.TYPE_REQUIRED')]
    public function getOoklaType(): ?string
    {
        return $this->ookla->type;
    }
}
