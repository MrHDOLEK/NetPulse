<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<HealthSample>
 */
final class HealthHistory extends Collection
{
    public static function of(HealthSample ...$samples): self
    {
        return new self(array_values($samples));
    }

    /**
     * @param list<HealthSample> $samples
     */
    public static function fromList(array $samples): self
    {
        return new self($samples);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function newest(): ?HealthSample
    {
        return $this->toArray()[0] ?? null;
    }

    public function leadingUnhealthyCount(): int
    {
        $count = 0;

        foreach ($this->toArray() as $sample) {
            if (!$sample->isUnhealthy()) {
                break;
            }

            $count++;
        }

        return $count;
    }

    public function newestAllHealthy(int $count): bool
    {
        return $this->newestAllSatisfy($count, static fn(HealthSample $sample): bool => $sample->isHealthy());
    }

    public function newestAllFailed(int $count): bool
    {
        return $this->newestAllSatisfy($count, static fn(HealthSample $sample): bool => $sample->failed);
    }

    /**
     * @return list<HealthSample>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }

    /**
     * @param callable(HealthSample): bool $predicate
     */
    private function newestAllSatisfy(int $count, callable $predicate): bool
    {
        $samples = $this->toArray();

        if ($count < 1 || count($samples) < $count) {
            return false;
        }

        for ($index = 0; $index < $count; $index++) {
            if (!$predicate($samples[$index])) {
                return false;
            }
        }

        return true;
    }
}
