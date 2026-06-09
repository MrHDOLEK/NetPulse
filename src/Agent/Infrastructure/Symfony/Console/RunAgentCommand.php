<?php

declare(strict_types=1);

namespace App\Agent\Infrastructure\Symfony\Console;

use App\Agent\Application\AgentTick;
use App\Agent\Application\TickSummary;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function function_exists;
use function max;
use function sleep;

#[AsCommand(
    name: "app:agent:run",
    description: "Run the probe agent: poll the server for due work, run Ookla, push results.",
)]
final class RunAgentCommand extends Command
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly AgentTick $tick,
        private readonly LoggerInterface $logger,
        #[Autowire("%env(int:AGENT_POLL_INTERVAL)%")]
        private readonly int $fallbackPollInterval,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            "once",
            null,
            InputOption::VALUE_NONE,
            "Run exactly one poll cycle and exit (instead of looping as a daemon).",
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $once = (bool)$input->getOption("once");

        if ($once) {
            $this->runTick($io);

            return Command::SUCCESS;
        }

        $this->registerSignalHandlers();
        $io->title("NetPulse agent started" . ($this->canHandleSignals() ? " (SIGTERM/SIGINT-aware)" : ""));
        $this->logger->info("agent loop started", [
            "fallbackPollInterval" => $this->fallbackPollInterval,
            "signalAware" => $this->canHandleSignals(),
        ]);

        while (!$this->shouldStop) {
            $summary = $this->runTick($io);
            $sleepFor = $this->pollSeconds($summary);

            $this->logger->info("agent loop sleeping", ["pollAfterSeconds" => $sleepFor]);

            $this->sleep($sleepFor);
        }

        $io->writeln("Agent stopped cleanly.");
        $this->logger->info("agent loop stopped cleanly");

        return Command::SUCCESS;
    }

    private function runTick(SymfonyStyle $io): ?TickSummary
    {
        try {
            $summary = $this->tick->run();
        } catch (Throwable $exception) {
            $io->warning("Agent tick failed: " . $exception->getMessage());
            $this->logger->error("agent cycle failed", ["error" => $exception->getMessage()]);

            return null;
        }

        $io->writeln(sprintf(
            "tick: %d task(s) — %d ok, %d failed, %d errored (poll after %ds)",
            $summary->tasks,
            $summary->succeeded,
            $summary->failed,
            $summary->errored,
            $summary->pollAfterSeconds,
        ));

        return $summary;
    }

    private function pollSeconds(?TickSummary $summary): int
    {
        $seconds = $summary === null ? 0 : $summary->pollAfterSeconds;

        if ($seconds <= 0) {
            $seconds = $this->fallbackPollInterval;
        }

        return max(1, $seconds);
    }

    private function sleep(int $seconds): void
    {
        for ($elapsed = 0; $elapsed < $seconds && !$this->shouldStop; $elapsed++) {
            sleep(1);

            if ($this->canHandleSignals()) {
                pcntl_signal_dispatch();
            }
        }
    }

    private function registerSignalHandlers(): void
    {
        if (!$this->canHandleSignals()) {
            return;
        }

        $handler = function (): void {
            $this->shouldStop = true;
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }

    private function canHandleSignals(): bool
    {
        return function_exists("pcntl_signal") && function_exists("pcntl_signal_dispatch");
    }
}
