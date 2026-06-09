<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\CreateConnection;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\ProbeRepository;
use App\Shared\Application\Service\IdGeneratorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: "command.bus")]
final readonly class CreateConnectionHandler
{
    public function __construct(
        private ConnectionRepository $connections,
        private ProbeRepository $probes,
        private IdGeneratorInterface $idGenerator,
    ) {}

    public function __invoke(CreateConnectionCommand $command): ConnectionCreated
    {
        $this->probes->get($command->probeId);

        $connectionId = new ConnectionId($this->idGenerator->generate()->toString());

        $connection = new Connection(
            $connectionId,
            $command->probeId,
            $command->name,
            $command->isp,
            $command->expected,
            $command->color,
            $command->labels,
            $command->serverPool,
            $command->schedule,
            true,
            $command->thresholds,
            $command->adaptivePolicy,
        );

        $this->connections->save($connection);

        return new ConnectionCreated($connectionId, $command->probeId);
    }
}
