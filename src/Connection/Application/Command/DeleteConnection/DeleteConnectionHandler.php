<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\DeleteConnection;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Exception\ConnectionNotFound;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: "command.bus")]
final readonly class DeleteConnectionHandler
{
    public function __construct(
        private ConnectionRepository $connections,
    ) {}

    public function __invoke(DeleteConnectionCommand $command): ConnectionDeleted
    {
        $connection = $this->connections->get($command->connectionId);

        if (!$connection->belongsTo($command->probeId)) {
            throw ConnectionNotFound::withId($command->connectionId);
        }

        $this->connections->delete($connection);

        return new ConnectionDeleted($command->connectionId);
    }
}
