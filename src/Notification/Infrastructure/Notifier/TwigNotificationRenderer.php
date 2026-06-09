<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Notifier;

use App\Notification\Application\Digest\ConnectionDigestCollection;
use App\Notification\Application\NotificationRenderer;
use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;
use App\Notification\Domain\ValueObject\Notification;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Twig\Environment;

#[AsAlias(id: NotificationRenderer::class)]
final readonly class TwigNotificationRenderer implements NotificationRenderer
{
    public function __construct(
        private Environment $twig,
    ) {}

    public function render(NotificationKind $kind, NotificationSeverity $severity, array $context): Notification
    {
        $rendered = $this->twig->render(
            "notification/{$kind->value}.txt.twig",
            $context + ["severity" => $severity->value],
        );

        [$subject, $body] = $this->split($rendered);

        return new Notification($kind, $severity, $subject, $body, $context);
    }

    public function renderDigest(string $period, ConnectionDigestCollection $digests): Notification
    {
        $rendered = $this->twig->render("notification/digest.txt.twig", [
            "period" => $period,
            "digests" => $digests->toArray(),
            "severity" => NotificationSeverity::Info->value,
        ]);

        [$subject, $body] = $this->split($rendered);

        return new Notification(
            NotificationKind::Digest,
            NotificationSeverity::Info,
            $subject,
            $body,
            [
                "period" => $period,
                "connections" => $digests->count(),
            ],
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $rendered): array
    {
        $normalized = trim($rendered);
        $parts = preg_split('/\R\s*\R/', $normalized, 2);

        if ($parts === false || count($parts) < 2) {
            return [trim($normalized), ""];
        }

        return [trim($parts[0]), trim($parts[1])];
    }
}
