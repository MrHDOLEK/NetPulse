<?php

declare(strict_types=1);

namespace App\Measurement\Application\Ookla;

final readonly class OoklaResult
{
    public function __construct(
        public ?string $type = null,
        public ?OoklaPing $ping = null,
        public ?OoklaBandwidth $download = null,
        public ?OoklaBandwidth $upload = null,
        public ?OoklaServer $server = null,
        public ?OoklaResultMeta $result = null,
        public ?float $packetLoss = null,
        public ?string $isp = null,
    ) {}

    public function isCompleted(): bool
    {
        return $this->type === "result"
            && $this->download?->bandwidth !== null
            && $this->upload?->bandwidth !== null
            && $this->ping?->latency !== null;
    }
}
