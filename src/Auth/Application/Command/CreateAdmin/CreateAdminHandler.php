<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\CreateAdmin;

use App\Auth\Application\WeakPassword;
use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserRole;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Shared\Application\Service\IdGeneratorInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

use function strlen;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateAdminHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasherFactoryInterface $hasherFactory,
        private IdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CreateAdminCommand $command): void
    {
        if (strlen($command->plainPassword) < WeakPassword::MIN_LENGTH) {
            throw WeakPassword::tooShort();
        }

        $email = new Email($command->email);

        if ($this->users->byEmail($email) !== null) {
            throw AdminAlreadyExists::withEmail($email->value());
        }

        $hash = $this->hasherFactory
            ->getPasswordHasher(PasswordAuthenticatedUserInterface::class)
            ->hash($command->plainPassword);

        $user = User::register(
            new UserId($this->idGenerator->generate()->toString()),
            $email,
            HashedPassword::fromHash($hash),
            new UserRoleCollection([UserRole::Admin]),
            $this->clock->now(),
        );

        $this->users->save($user);
    }
}
