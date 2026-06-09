<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\DeleteProbe;

use App\Connection\Domain\ConnectionRepository;
use App\Probe\Domain\Exception\ProbeHasConnections;
use App\Probe\Domain\ProbeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: "command.bus")]
final readonly class DeleteProbeHandler
{
    public function __construct(
        private ProbeRepository $probes,
        private ConnectionRepository $connections,
    ) {}

    public function __invoke(DeleteProbeCommand $command): ProbeDeleted
    {
        $probe = $this->probes->get($command->probeId);

        if ($this->connections->byProbe($command->probeId)->count() > 0) {
            throw ProbeHasConnections::withId($command->probeId);
        }

        $this->probes->delete($probe);

        return new ProbeDeleted($command->probeId);
    }
}
