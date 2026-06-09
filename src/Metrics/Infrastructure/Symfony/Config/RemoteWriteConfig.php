<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\Symfony\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RemoteWriteConfig
{
    public ?string $auth;

    /** @var array<string, string> */
    public array $extraLabels;

    public function __construct(
        #[Autowire("%netpulse.remote_write.enabled%")]
        public bool $enabled,
        #[Autowire("%netpulse.remote_write.url%")]
        public string $url,
        #[Autowire("%netpulse.remote_write.auth%")]
        ?string $auth,
        #[Autowire("%netpulse.remote_write.extra_labels%")]
        string $extraLabels,
    ) {
        $this->auth = ($auth === null || $auth === "") ? null : $auth;
        $this->extraLabels = self::parseExtraLabels($extraLabels);
    }

    public static function fromRaw(
        bool $enabled,
        string $url,
        ?string $auth,
        string $extraLabelsRaw,
    ): self {
        return new self($enabled, $url, $auth, $extraLabelsRaw);
    }

    /**
     * @return array<string, string>
     */
    private static function parseExtraLabels(string $raw): array
    {
        $extraLabels = [];

        foreach (array_filter(explode(",", $raw)) as $pair) {
            $parts = explode("=", $pair, 2);

            if (count($parts) === 2 && trim($parts[0]) !== "") {
                $extraLabels[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $extraLabels;
    }
}
