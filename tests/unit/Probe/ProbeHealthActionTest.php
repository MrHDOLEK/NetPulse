<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe;

use App\Probe\Application\Api\ProbeHealthAction;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

final class ProbeHealthActionTest extends TestCase
{
    private const string NOW = '2026-06-08 12:00:00';

    public function testHealthyWhenPolledRecently(): void
    {
        $response = $this->action()($this->probe(new DateTimeImmutable(self::NOW . ' -30 seconds')));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($this->body($response)['healthy']);
        self::assertSame(30, $this->body($response)['secondsSincePoll']);
    }

    public function testUnhealthyWhenPollIsStale(): void
    {
        $response = $this->action()($this->probe(new DateTimeImmutable(self::NOW . ' -10 minutes')));

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertFalse($this->body($response)['healthy']);
    }

    public function testUnhealthyWhenNeverPolled(): void
    {
        $response = $this->action()($this->probe(null));

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertFalse($this->body($response)['healthy']);
        self::assertNull($this->body($response)['lastPollAtUnix']);
    }

    private function action(): ProbeHealthAction
    {
        $clock = new class(self::NOW) implements ClockInterface {
            public function __construct(
                private readonly string $now,
            ) {}

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->now);
            }
        };

        return new ProbeHealthAction($clock, 60);
    }

    private function probe(?DateTimeImmutable $lastPollAt): Probe
    {
        return new Probe(
            new ProbeId('019ea6ad-7a08-7137-9923-d00b5fb78766'),
            'home',
            Labels::empty(),
            'token-hash',
            true,
            new DateTimeImmutable(self::NOW . ' -1 day'),
            $lastPollAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
