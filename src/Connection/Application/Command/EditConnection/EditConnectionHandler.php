<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\EditConnection;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Exception\ConnectionNotFound;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditConnectionHandler
{
    public function __construct(
        private ConnectionRepository $connections,
    ) {}

    public function __invoke(EditConnectionCommand $command): ConnectionUpdated
    {
        $connection = $this->connections->get($command->connectionId);

        if (!$connection->belongsTo($command->probeId)) {
            throw ConnectionNotFound::withId($command->connectionId);
        }

        $connection->reconfigure(
            $command->name,
            $command->isp,
            $command->expected,
            $command->color,
            $command->labels,
            $command->serverPool,
            $command->schedule,
            $command->thresholds,
            $command->adaptivePolicy,
        );

        $this->connections->save($connection);

        return new ConnectionUpdated($command->connectionId, $command->probeId);
    }
}
