<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\SetConnectionEnabled;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Exception\ConnectionNotFound;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SetConnectionEnabledHandler
{
    public function __construct(
        private ConnectionRepository $connections,
    ) {}

    public function __invoke(SetConnectionEnabledCommand $command): ConnectionEnabledSet
    {
        $connection = $this->connections->get($command->connectionId);

        if (!$connection->belongsTo($command->probeId)) {
            throw ConnectionNotFound::withId($command->connectionId);
        }

        if ($command->enabled) {
            $connection->enable();
        } else {
            $connection->disable();
        }

        $this->connections->save($connection);

        return new ConnectionEnabledSet($command->connectionId, $command->enabled);
    }
}
