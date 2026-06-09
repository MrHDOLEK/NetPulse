<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PrometheusConfig
{
    /** @var list<string> */
    private array $allowedCidrs;

    public function __construct(
        #[Autowire("%netpulse.prometheus.metrics_enabled%")]
        private bool $metricsEnabled,
        #[Autowire("%netpulse.prometheus.allowed_ips%")]
        string $allowedIpsRaw,
        #[Autowire("%netpulse.prometheus.freshness_window%")]
        private int $freshnessWindowSeconds,
    ) {
        $this->allowedCidrs = $this->parseCidrs($allowedIpsRaw);
    }

    public function metricsEnabled(): bool
    {
        return $this->metricsEnabled;
    }

    /**
     * @return list<string>
     */
    public function allowedCidrs(): array
    {
        return $this->allowedCidrs;
    }

    public function freshnessWindowSeconds(): int
    {
        return $this->freshnessWindowSeconds;
    }

    /**
     * @return list<string>
     */
    private function parseCidrs(string $raw): array
    {
        $parts = explode(",", $raw);
        $cidrs = [];

        foreach ($parts as $part) {
            $trimmed = trim($part);

            if ($trimmed !== "") {
                $cidrs[] = $trimmed;
            }
        }

        return $cidrs;
    }
}
