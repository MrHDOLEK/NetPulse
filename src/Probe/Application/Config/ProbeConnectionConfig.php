<?php

declare(strict_types=1);

namespace App\Probe\Application\Config;

final readonly class ProbeConnectionConfig
{
    /**
     * @param array<string,string> $labels
     * @param list<string> $serverPool
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $labels,
        public array $serverPool,
        public bool $enabled,
    ) {}

    /**
     * @return array{id:string,name:string,labels:array<string,string>,serverPool:list<string>,enabled:bool}
     */
    public function toArray(): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "labels" => $this->labels,
            "serverPool" => $this->serverPool,
            "enabled" => $this->enabled,
        ];
    }
}
