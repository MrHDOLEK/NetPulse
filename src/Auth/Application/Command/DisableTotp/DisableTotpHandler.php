<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\DisableTotp;

use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DisableTotpHandler
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function __invoke(DisableTotpCommand $command): void
    {
        $user = $this->users->get(new UserId($command->userId));

        if (!$user->hasTotp()) {
            return;
        }

        $user->disableTotp();
        $this->users->save($user);
    }
}
