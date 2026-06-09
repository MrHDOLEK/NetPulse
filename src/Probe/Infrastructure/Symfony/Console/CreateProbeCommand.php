<?php

declare(strict_types=1);

namespace App\Probe\Infrastructure\Symfony\Console;

use App\Probe\Application\Command\CreateProbe\CreateProbeCommand as CreateProbe;
use App\Probe\Application\Command\CreateProbe\CreateProbeHandler;
use App\Shared\Domain\ValueObject\Labels;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function explode;
use function is_string;
use function sprintf;
use function str_contains;

#[AsCommand(
    name: "app:probe:create",
    description: "Create a probe and print its one-time plaintext token.",
)]
final class CreateProbeCommand extends Command
{
    public function __construct(
        private readonly CreateProbeHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument("name", InputArgument::REQUIRED, "Human-readable probe name")
            ->addOption(
                "label",
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Prometheus label in key=value form (repeatable)",
                [],
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument("name");

        if (!is_string($name)) {
            $io->error("The probe name must be a string.");

            return Command::FAILURE;
        }

        /** @var array<int,string> $rawLabels */
        $rawLabels = $input->getOption("label");

        $labels = [];

        foreach ($rawLabels as $rawLabel) {
            if (!str_contains($rawLabel, "=")) {
                $io->error(sprintf("Invalid label \"%s\": expected key=value.", $rawLabel));

                return Command::FAILURE;
            }

            [$key, $value] = explode("=", $rawLabel, 2);

            if ($key === "") {
                $io->error(sprintf("Invalid label \"%s\": empty key.", $rawLabel));

                return Command::FAILURE;
            }

            $labels[$key] = $value;
        }

        $result = ($this->handler)(new CreateProbe($name, Labels::fromArray($labels)));

        $io->success("Probe created");
        $io->writeln(sprintf("Probe ID: %s", $result->probeId->toString()));
        $io->writeln(sprintf("Token:    %s", $result->plaintextToken));
        $io->warning("Store the token now — it is shown only once.");

        return Command::SUCCESS;
    }
}
