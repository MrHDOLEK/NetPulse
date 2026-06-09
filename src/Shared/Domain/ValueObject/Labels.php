<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class Labels
{
    /**
     * @param array<string, string> $labels
     */
    private function __construct(
        private array $labels,
    ) {}

    /**
     * @param array<string, string> $labels
     */
    public static function fromArray(array $labels): self
    {
        return new self($labels);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function get(string $key): ?string
    {
        return $this->labels[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->labels[$key]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->labels;
    }

    public function isEmpty(): bool
    {
        return $this->labels === [];
    }
}
