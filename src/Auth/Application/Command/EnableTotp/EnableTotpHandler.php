<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\EnableTotp;

use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\TotpSecret;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EnableTotpHandler
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function __invoke(EnableTotpCommand $command): void
    {
        $user = $this->users->get(new UserId($command->userId));
        $user->enableTotp(new TotpSecret($command->secret), $command->hashedRecoveryCodes);
        $this->users->save($user);
    }
}
