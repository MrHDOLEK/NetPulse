<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\RotateProbeToken;

use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeToken;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: "command.bus")]
final readonly class RotateProbeTokenHandler
{
    public function __construct(
        private ProbeRepository $probes,
        private ProbeTokenHasher $hasher,
    ) {}

    public function __invoke(RotateProbeTokenCommand $command): ProbeTokenRotated
    {
        $probe = $this->probes->get($command->probeId);

        $token = ProbeToken::generate();
        $probe->rotateToken($this->hasher->hash($token->toString()));

        $this->probes->save($probe);

        return new ProbeTokenRotated($command->probeId, $token->toString());
    }
}
