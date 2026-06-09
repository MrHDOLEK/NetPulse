<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Symfony\Console;

use App\Notification\Application\Command\GenerateDigest\GenerateDigestHandler;
use App\Notification\Application\Digest\ConnectionDigest;
use App\Notification\Application\Digest\ConnectionDigestCollection;
use App\Notification\Application\Digest\DigestRepository;
use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;
use App\Notification\Domain\NotificationDispatcher;
use App\Notification\Domain\ValueObject\Notification;
use App\Notification\Infrastructure\Notifier\TwigNotificationRenderer;
use App\Notification\Infrastructure\Symfony\Console\SendDigestCommand;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;

final class SendDigestCommandTest extends TestCase
{
    private const string TEMPLATES_DIR = __DIR__ . "/../../../../../../templates/notification";

    public function testDailyPeriodDispatchesOneDigestWithPerConnectionContent(): void
    {
        $dispatcher = new RecordingDigestDispatcher();
        $digests = ConnectionDigestCollection::of(
            new ConnectionDigest("home", "wan1", 250_000_000, 50_000_000, 12.3, 0.01, 0.9, 20, 2),
            new ConnectionDigest("home", "wan2", 80_000_000, 8_000_000, 30.0, 0.05, 0.5, 10, 4),
        );

        $tester = new CommandTester($this->command($dispatcher, new FixedDigestRepository($digests)));
        $exitCode = $tester->execute(["--period" => "daily"]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $dispatcher->sent);

        $notification = $dispatcher->sent[0];
        self::assertSame(NotificationKind::Digest, $notification->kind);
        self::assertSame(NotificationSeverity::Info, $notification->severity);
        self::assertSame("[NetPulse] daily digest — 2 connections", $notification->subject);
        self::assertStringContainsString("home/wan1", $notification->body);
        self::assertStringContainsString("down 250 Mbps", $notification->body);
        self::assertStringContainsString("home/wan2", $notification->body);
        self::assertStringContainsString("20 tests, 2 failures", $notification->body);
        self::assertSame(["period" => "daily", "connections" => 2], $notification->context);
    }

    public function testWeeklyPeriodIsAccepted(): void
    {
        $dispatcher = new RecordingDigestDispatcher();
        $digests = ConnectionDigestCollection::of(
            new ConnectionDigest("home", "wan1", 100_000_000, 10_000_000, 9.0, 0.0, 1.0, 5, 0),
        );

        $tester = new CommandTester($this->command($dispatcher, new FixedDigestRepository($digests)));
        $exitCode = $tester->execute(["--period" => "weekly"]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $dispatcher->sent);
        self::assertSame("[NetPulse] weekly digest — 1 connection", $dispatcher->sent[0]->subject);
    }

    public function testDefaultPeriodIsDaily(): void
    {
        $dispatcher = new RecordingDigestDispatcher();
        $readModel = new FixedDigestRepository(ConnectionDigestCollection::of(
            new ConnectionDigest("home", "wan1", 100_000_000, 10_000_000, 9.0, 0.0, 1.0, 5, 0),
        ));

        $tester = new CommandTester($this->command($dispatcher, $readModel));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $dispatcher->sent);
        self::assertStringContainsString("daily digest", $dispatcher->sent[0]->subject);

        self::assertEquals(new DateTimeImmutable("2026-06-05 08:00:00"), $readModel->lastSince);
    }

    public function testWeeklyWindowOpensSevenDaysBeforeNow(): void
    {
        $dispatcher = new RecordingDigestDispatcher();
        $readModel = new FixedDigestRepository(ConnectionDigestCollection::of(
            new ConnectionDigest("home", "wan1", 100_000_000, 10_000_000, 9.0, 0.0, 1.0, 5, 0),
        ));

        $tester = new CommandTester($this->command($dispatcher, $readModel));
        $tester->execute(["--period" => "weekly"]);

        self::assertEquals(new DateTimeImmutable("2026-05-30 08:00:00"), $readModel->lastSince);
    }

    public function testEmptyDataDispatchesNothing(): void
    {
        $dispatcher = new RecordingDigestDispatcher();

        $tester = new CommandTester($this->command($dispatcher, new FixedDigestRepository(ConnectionDigestCollection::fromList([]))));
        $exitCode = $tester->execute(["--period" => "daily"]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(0, $dispatcher->sent);
    }

    public function testInvalidPeriodFailsWithoutDispatching(): void
    {
        $dispatcher = new RecordingDigestDispatcher();

        $tester = new CommandTester($this->command($dispatcher, new FixedDigestRepository(ConnectionDigestCollection::fromList([]))));
        $exitCode = $tester->execute(["--period" => "hourly"]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertCount(0, $dispatcher->sent);
        self::assertStringContainsString("Invalid --period", $tester->getDisplay());
    }

    private function command(
        RecordingDigestDispatcher $dispatcher,
        FixedDigestRepository $readModel,
    ): SendDigestCommand {
        $twig = new Environment(new FilesystemLoader(dirname(self::TEMPLATES_DIR)));
        $renderer = new TwigNotificationRenderer($twig);

        $handler = new GenerateDigestHandler(
            $readModel,
            $renderer,
            $dispatcher,
            new MockClock("2026-06-06 08:00:00"),
            new NullLogger(),
        );

        return new SendDigestCommand($handler);
    }
}

final class RecordingDigestDispatcher implements NotificationDispatcher
{
    /** @var list<Notification> */
    public array $sent = [];

    public function send(Notification $notification): void
    {
        $this->sent[] = $notification;
    }
}

final class FixedDigestRepository implements DigestRepository
{
    public ?DateTimeImmutable $lastSince = null;

    public function __construct(
        private readonly ConnectionDigestCollection $digests,
    ) {}

    public function since(DateTimeImmutable $since): ConnectionDigestCollection
    {
        $this->lastSince = $since;

        return $this->digests;
    }
}
