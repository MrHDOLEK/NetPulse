<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Symfony\Console;

use App\Auth\Application\Command\CreateAdmin\AdminAlreadyExists;
use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use App\Auth\Application\WeakPassword;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

use function is_string;
use function trim;

#[AsCommand(
    name: "app:user:create",
    description: "Create an administrator account (ROLE_ADMIN) with a hashed password.",
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption("email", null, InputOption::VALUE_REQUIRED, "Email address of the new administrator")
            ->addOption("password", null, InputOption::VALUE_REQUIRED, "Plaintext password (omit for a secure hidden prompt)");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $this->resolveEmail($input, $io);

        if ($email === null) {
            return Command::FAILURE;
        }

        $password = $this->resolvePassword($input, $io);

        if ($password === null) {
            return Command::FAILURE;
        }

        try {
            $this->commandBus->dispatch(new CreateAdminCommand($email, $password));
        } catch (HandlerFailedException $exception) {
            $io->error($this->friendlyMessage($exception));

            return Command::FAILURE;
        }

        $io->success("Created administrator " . $email . ".");

        return Command::SUCCESS;
    }

    private function resolveEmail(InputInterface $input, SymfonyStyle $io): ?string
    {
        $raw = $input->getOption("email");
        $email = is_string($raw) ? trim($raw) : "";

        if ($email === "" && $input->isInteractive()) {
            $answer = $io->ask("Email address");
            $email = is_string($answer) ? trim($answer) : "";
        }

        if ($email === "") {
            $io->error("The --email option is required.");

            return null;
        }

        return $email;
    }

    private function resolvePassword(InputInterface $input, SymfonyStyle $io): ?string
    {
        $raw = $input->getOption("password");

        if (is_string($raw) && $raw !== "") {
            return $raw;
        }

        if (!$input->isInteractive()) {
            $io->error("The --password option is required in non-interactive mode.");

            return null;
        }

        $password = $this->askHidden($input, $io, "Password (hidden)");
        $confirm = $this->askHidden($input, $io, "Repeat password (hidden)");

        if ($password !== $confirm) {
            $io->error("The passwords do not match.");

            return null;
        }

        if ($password === "") {
            $io->error("The password must not be empty.");

            return null;
        }

        return $password;
    }

    private function askHidden(InputInterface $input, SymfonyStyle $io, string $prompt): string
    {
        $question = new Question($prompt);
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $helper = $this->getHelper("question");

        if (!$helper instanceof QuestionHelper) {
            return "";
        }

        $answer = $helper->ask($input, $io, $question);

        return is_string($answer) ? $answer : "";
    }

    private function friendlyMessage(HandlerFailedException $exception): string
    {
        $cause = $this->unwrap($exception);

        return match (true) {
            $cause instanceof WeakPassword => "The password must be at least 12 characters long.",
            $cause instanceof AdminAlreadyExists => "An administrator account already exists for this email.",
            $cause instanceof InvalidArgumentException => "Please enter a valid email address.",
            default => "Could not create the administrator. Please check your details and try again.",
        };
    }

    private function unwrap(HandlerFailedException $exception): Throwable
    {
        foreach ($exception->getWrappedExceptions() as $wrapped) {
            return $wrapped;
        }

        return $exception->getPrevious() ?? $exception;
    }
}
