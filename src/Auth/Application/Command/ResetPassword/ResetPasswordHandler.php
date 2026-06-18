<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ResetPassword;

use App\Auth\Application\WeakPassword;
use App\Auth\Domain\Entity\User\UserNotFound;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

use function sprintf;
use function strlen;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ResetPasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasherFactoryInterface $hasherFactory,
    ) {}

    public function __invoke(ResetPasswordCommand $command): void
    {
        if (strlen($command->plainPassword) < WeakPassword::MIN_LENGTH) {
            throw WeakPassword::tooShort();
        }

        $email = new Email($command->email);
        $user = $this->users->byEmail($email);

        if ($user === null) {
            throw new UserNotFound(sprintf('User %s not found.', $email->value()));
        }

        $hash = $this->hasherFactory
            ->getPasswordHasher(PasswordAuthenticatedUserInterface::class)
            ->hash($command->plainPassword);

        $user->changePassword(HashedPassword::fromHash($hash));

        $this->users->save($user);
    }
}
