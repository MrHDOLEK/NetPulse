<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\CreateProbe;

use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Probe\Domain\ValueObject\ProbeToken;
use App\Shared\Application\Service\IdGeneratorInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: "command.bus")]
final class CreateProbeHandler
{
    public function __construct(
        private readonly ProbeRepository $repository,
        private readonly ProbeTokenHasher $hasher,
        private readonly IdGeneratorInterface $idGenerator,
        private readonly ClockInterface $clock,
    ) {}

    public function __invoke(CreateProbeCommand $command): ProbeCreated
    {
        $probeId = new ProbeId($this->idGenerator->generate()->toString());
        $token = ProbeToken::generate();

        $probe = new Probe(
            $probeId,
            $command->name,
            $command->labels,
            $this->hasher->hash($token->toString()),
            true,
            $this->clock->now(),
        );

        $this->repository->save($probe);

        return new ProbeCreated($probeId, $token->toString());
    }
}
