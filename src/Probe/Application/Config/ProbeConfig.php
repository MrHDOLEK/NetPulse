<?php

declare(strict_types=1);

namespace App\Probe\Application\Config;

final readonly class ProbeConfig
{
    /**
     * @param list<ProbeConnectionConfig> $connections
     */
    public function __construct(
        public string $probeId,
        public bool $probeEnabled,
        public array $connections,
    ) {}

    /**
     * @return array{
     *     probe:array{id:string,enabled:bool},
     *     connections:list<array{id:string,name:string,labels:array<string,string>,serverPool:list<string>,enabled:bool}>
     * }
     */
    public function toArray(): array
    {
        return [
            'probe' => [
                'id' => $this->probeId,
                'enabled' => $this->probeEnabled,
            ],
            'connections' => array_map(
                static fn(ProbeConnectionConfig $connection): array => $connection->toArray(),
                $this->connections,
            ),
        ];
    }
}
