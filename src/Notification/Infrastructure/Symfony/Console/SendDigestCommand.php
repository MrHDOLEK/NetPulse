<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Symfony\Console;

use App\Notification\Application\Command\GenerateDigest\GenerateDigestCommand;
use App\Notification\Application\Command\GenerateDigest\GenerateDigestHandler;
use App\Notification\Application\Command\GenerateDigest\GenerateDigestPeriod;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use ValueError;

use function array_map;
use function implode;
use function is_string;

#[AsCommand(
    name: 'app:notifications:digest',
    description: 'Build and dispatch the daily or weekly health digest for every connection.',
)]
final class SendDigestCommand extends Command
{
    public function __construct(
        private readonly GenerateDigestHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'period',
            null,
            InputOption::VALUE_REQUIRED,
            'Digest window: daily (last 24h) or weekly (last 7 days)',
            GenerateDigestPeriod::Daily->value,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $periodRaw = $input->getOption('period');

        try {
            $period = GenerateDigestPeriod::from(is_string($periodRaw) ? $periodRaw : '');
        } catch (ValueError) {
            $allowed = implode(', ', array_map(
                static fn(GenerateDigestPeriod $case): string => $case->value,
                GenerateDigestPeriod::cases(),
            ));
            $io->error('Invalid --period; expected one of: ' . $allowed . '.');

            return Command::FAILURE;
        }

        ($this->handler)(new GenerateDigestCommand($period->value));

        $io->success($period->value . ' digest dispatched (or skipped if there was no data).');

        return Command::SUCCESS;
    }
}
