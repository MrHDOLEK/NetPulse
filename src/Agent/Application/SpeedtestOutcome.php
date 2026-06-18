<?php

declare(strict_types=1);

namespace App\Agent\Application;

final readonly class SpeedtestOutcome
{
    public const string FAILED_TYPE = 'error';

    /**
     * @param array<string,mixed>|null $ooklaJson decoded Ookla JSON on success, null on failure
     */
    private function __construct(
        public bool $successful,
        private ?array $ooklaJson,
        public ?string $errorMessage,
    ) {}

    /**
     * @param array<string,mixed> $ooklaJson verbatim decoded Ookla CLI JSON ("type": "result")
     */
    public static function success(array $ooklaJson): self
    {
        return new self(true, $ooklaJson, null);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(false, null, $errorMessage);
    }

    /**
     * @return array<string,mixed>
     */
    public function toOoklaJson(?string $serverId): array
    {
        if ($this->successful && $this->ooklaJson !== null) {
            return $this->ooklaJson;
        }

        $payload = [
            'type' => self::FAILED_TYPE,
            'message' => $this->errorMessage ?? 'speedtest failed',
        ];

        if ($serverId !== null) {
            $payload['server'] = ['id' => $serverId];
        }

        return $payload;
    }
}
