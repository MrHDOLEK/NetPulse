<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\SetProbeEnabled;

use App\Probe\Domain\ProbeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SetProbeEnabledHandler
{
    public function __construct(
        private ProbeRepository $probes,
    ) {}

    public function __invoke(SetProbeEnabledCommand $command): ProbeEnabledSet
    {
        $probe = $this->probes->get($command->probeId);

        if ($command->enabled) {
            $probe->enable();
        } else {
            $probe->disable();
        }

        $this->probes->save($probe);

        return new ProbeEnabledSet($command->probeId, $command->enabled);
    }
}
