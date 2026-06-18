<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Symfony\Console;

use App\Connection\Application\Command\CreateConnection\ConnectionCreated;
use App\Connection\Application\Command\CreateConnection\CreateConnectionCommand as CreateConnection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\Enum\ScheduleMode;
use App\Connection\Domain\Exception\InvalidAdaptivePolicy;
use App\Connection\Domain\Exception\InvalidThresholds;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Connection\Infrastructure\Symfony\Request\ConnectionInputMapper;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\InvalidId;
use App\Shared\Domain\NotFoundException;
use App\Shared\Domain\ValueObject\Labels;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use ValueError;

use function is_array;
use function is_numeric;
use function is_string;
use function trim;

#[AsCommand(
    name: 'app:connection:create',
    description: 'Create a monitored connection (WAN link) for an existing probe.',
)]
final class CreateConnectionCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly ProbeRepository $probeRepository,
        private readonly ConnectionInputMapper $inputMapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Human-readable connection name')
            ->addOption('probe', null, InputOption::VALUE_REQUIRED, 'UUID of the probe that measures this link')
            ->addOption('isp', null, InputOption::VALUE_REQUIRED, 'Internet service provider name', '')
            ->addOption('download-mbps', null, InputOption::VALUE_REQUIRED, 'Expected download speed in Mbps', '0')
            ->addOption('upload-mbps', null, InputOption::VALUE_REQUIRED, 'Expected upload speed in Mbps', '0')
            ->addOption(
                'color',
                null,
                InputOption::VALUE_REQUIRED,
                'UI accent: primary, violet or amber',
                ConnectionColor::default()->value,
            )
            ->addOption('labels', null, InputOption::VALUE_REQUIRED, 'Comma-separated key=value Prometheus labels', '')
            ->addOption('server-pool', null, InputOption::VALUE_REQUIRED, 'Comma-separated Ookla server hosts', '')
            ->addOption(
                'schedule-mode',
                null,
                InputOption::VALUE_REQUIRED,
                'Scheduling mode: cron or even',
                ScheduleMode::Even->value,
            )
            ->addOption(
                'cron',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Cron expression (repeatable; required for --schedule-mode=cron)',
            )
            ->addOption('tests-per-day', null, InputOption::VALUE_REQUIRED, 'Even mode: number of tests per day', '24')
            ->addOption('jitter', null, InputOption::VALUE_REQUIRED, 'Even mode: jitter window in seconds', '120')
            ->addOption(
                'min-download-ratio',
                null,
                InputOption::VALUE_REQUIRED,
                'Health: minimum download ratio vs expected (0 < r <= 1)',
            )
            ->addOption(
                'min-upload-ratio',
                null,
                InputOption::VALUE_REQUIRED,
                'Health: minimum upload ratio vs expected (0 < r <= 1)',
            )
            ->addOption(
                'max-ping-ms',
                null,
                InputOption::VALUE_REQUIRED,
                "Health: max ping in ms (pass 'none' to disable)",
            )
            ->addOption(
                'max-jitter-ms',
                null,
                InputOption::VALUE_REQUIRED,
                "Health: max jitter in ms (pass 'none' to disable)",
            )
            ->addOption(
                'max-packet-loss',
                null,
                InputOption::VALUE_REQUIRED,
                "Health: max packet-loss ratio 0..1 (pass 'none' to disable)",
            )
            ->addOption(
                'adaptive-interval',
                null,
                InputOption::VALUE_REQUIRED,
                'Adaptive: densified interval in seconds when degraded (>= 1)',
            )
            ->addOption(
                'recovery-count',
                null,
                InputOption::VALUE_REQUIRED,
                'Adaptive: consecutive healthy measurements to recover (>= 1)',
            )
            ->addOption(
                'max-failures',
                null,
                InputOption::VALUE_REQUIRED,
                'Adaptive: consecutive failures before backoff (>= 1)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $probeIdRaw = $input->getOption('probe');

        if (!is_string($probeIdRaw) || trim($probeIdRaw) === '') {
            $io->error('The --probe option is required.');

            return Command::FAILURE;
        }

        try {
            $probeId = new ProbeId(trim($probeIdRaw));
        } catch (InvalidId) {
            $io->error('The --probe option is not a valid UUID.');

            return Command::FAILURE;
        }

        if ($this->probeRepository->find($probeId) === null) {
            $io->error('Probe ' . $probeId->toString() . ' was not found.');

            return Command::FAILURE;
        }

        try {
            $color = ConnectionColor::from($this->stringOption($input, 'color'));
        } catch (ValueError) {
            $io->error('Invalid --color; expected one of: primary, violet, amber.');

            return Command::FAILURE;
        }

        $schedule = $this->buildSchedule($input, $io);

        if ($schedule === null) {
            return Command::FAILURE;
        }

        try {
            $thresholds = $this->buildThresholds($input);
            $adaptivePolicy = $this->buildAdaptivePolicy($input);
        } catch (InvalidThresholds|InvalidAdaptivePolicy $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $command = new CreateConnection(
            $probeId,
            $this->stringArgument($input, 'name'),
            $this->stringOption($input, 'isp'),
            new ExpectedSpeed(
                $this->megabitsToBits($input->getOption('download-mbps')),
                $this->megabitsToBits($input->getOption('upload-mbps')),
            ),
            $color,
            Labels::fromArray($this->inputMapper->parseLabels($this->stringOption($input, 'labels'))),
            ServerPool::fromArray($this->inputMapper->parseList($this->stringOption($input, 'server-pool'))),
            $schedule,
            $thresholds,
            $adaptivePolicy,
        );

        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof NotFoundException) {
                $io->error('Probe ' . $probeId->toString() . ' was not found.');

                return Command::FAILURE;
            }

            throw $exception;
        }

        $handled = $envelope->last(HandledStamp::class);
        $result = $handled?->getResult();
        $connectionId = $result instanceof ConnectionCreated ? $result->connectionId->toString() : '';

        $io->success('Created connection ' . $connectionId . ' for probe ' . $probeId->toString() . '.');

        return Command::SUCCESS;
    }

    private function stringArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return is_string($value) ? trim($value) : '';
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_string($value) ? trim($value) : '';
    }

    private function buildSchedule(InputInterface $input, SymfonyStyle $io): ?Schedule
    {
        $modeRaw = $this->stringOption($input, 'schedule-mode');

        try {
            $mode = ScheduleMode::from($modeRaw);
        } catch (ValueError) {
            $io->error('Invalid --schedule-mode; expected one of: cron, even.');

            return null;
        }

        $cron = [];
        $raw = $input->getOption('cron');

        if (is_array($raw)) {
            foreach ($raw as $expression) {
                if (is_string($expression)) {
                    $cron[] = $expression;
                }
            }
        }

        if ($mode === ScheduleMode::Even) {
            $testsPerDayRaw = $input->getOption('tests-per-day');
            $jitterRaw = $input->getOption('jitter');

            if (!is_numeric($testsPerDayRaw)) {
                $io->error('--tests-per-day must be an integer >= 1.');

                return null;
            }

            if (!is_numeric($jitterRaw)) {
                $io->error('--jitter must be an integer >= 0.');

                return null;
            }

            $testsPerDay = (int) $testsPerDayRaw;
            $jitter = (int) $jitterRaw;
        } else {
            $testsPerDay = 0;
            $jitter = 0;
        }

        try {
            return $this->inputMapper->buildSchedule($mode->value, $cron, $testsPerDay, $jitter);
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return null;
        }
    }

    private function buildThresholds(InputInterface $input): Thresholds
    {
        return $this->inputMapper->buildThresholds(
            $this->nullableFloatOption($input, 'min-download-ratio'),
            $this->nullableFloatOption($input, 'min-upload-ratio'),
            $this->capOption($input, 'max-ping-ms', Thresholds::default()->maxPingMs()),
            $this->capOption($input, 'max-jitter-ms', Thresholds::default()->maxJitterMs()),
            $this->capOption($input, 'max-packet-loss', Thresholds::default()->maxPacketLossRatio()),
        );
    }

    private function buildAdaptivePolicy(InputInterface $input): AdaptivePolicy
    {
        return $this->inputMapper->buildAdaptivePolicy(
            $this->nullableIntOption($input, 'adaptive-interval'),
            $this->nullableIntOption($input, 'recovery-count'),
            $this->nullableIntOption($input, 'max-failures'),
        );
    }

    private function nullableFloatOption(InputInterface $input, string $name): ?float
    {
        $value = $input->getOption($name);

        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableIntOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);

        return is_numeric($value) ? (int) $value : null;
    }

    private function capOption(InputInterface $input, string $name, ?float $default): ?float
    {
        $value = $input->getOption($name);

        if ($value === null) {
            return $default;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function megabitsToBits(mixed $value): int
    {
        $megabits = is_string($value) ? (int) $value : 0;

        return $this->inputMapper->megabitsToBits($megabits);
    }
}
