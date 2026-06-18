<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\RegenerateRecoveryCodes;

use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegenerateRecoveryCodesHandler
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function __invoke(RegenerateRecoveryCodesCommand $command): void
    {
        $user = $this->users->get(new UserId($command->userId));
        $user->replaceRecoveryCodes($command->hashedRecoveryCodes);
        $this->users->save($user);
    }
}
