<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<NotificationSendRow>
 */
final class NotificationSendRowCollection extends Collection
{
    public static function of(NotificationSendRow ...$rows): self
    {
        return new self(array_values($rows));
    }

    /**
     * @param list<NotificationSendRow> $rows
     */
    public static function fromList(array $rows): self
    {
        return new self($rows);
    }

    /**
     * @return list<NotificationSendRow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
