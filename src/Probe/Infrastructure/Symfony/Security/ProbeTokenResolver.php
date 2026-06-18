<?php

declare(strict_types=1);

namespace App\Probe\Infrastructure\Symfony\Security;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Exception\ConnectionNotOwnedByProbe;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\Exception\InvalidProbeToken;
use App\Probe\Domain\Exception\ProbeDisabled;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeId;
use Generator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[AutoconfigureTag('controller.argument_value_resolver', ['priority' => -40])]
final class ProbeTokenResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ProbeRepository $probes,
        private readonly ConnectionRepository $connections,
        private readonly ProbeTokenHasher $tokenHasher,
    ) {}

    /**
     * @return Generator<Probe>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== Probe::class) {
            return;
        }

        $probe = $this->authenticate($request);
        $this->guardConnectionOwnership($request, $probe);

        $request->attributes->set('_probe', $probe);

        yield $probe;
    }

    private function authenticate(Request $request): Probe
    {
        $token = $this->extractToken($request);

        $probeIdAttribute = $request->attributes->get('probeId');
        $probeId = new ProbeId(is_string($probeIdAttribute) ? $probeIdAttribute : '');

        $probe = $this->probes->get($probeId);

        if (!$probe->verifyToken($token, $this->tokenHasher)) {
            throw new InvalidProbeToken();
        }

        if (!$probe->isEnabled()) {
            throw new ProbeDisabled();
        }

        return $probe;
    }

    private function extractToken(Request $request): string
    {
        $header = $request->headers->get('Authorization');

        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            throw new InvalidProbeToken();
        }

        $token = trim(substr($header, 7));

        if ($token === '') {
            throw new InvalidProbeToken();
        }

        return $token;
    }

    private function guardConnectionOwnership(Request $request, Probe $probe): void
    {
        $connectionId = $this->extractConnectionId($request);

        if ($connectionId === null) {
            return;
        }

        $connection = $this->connections->find(new ConnectionId($connectionId));

        if ($connection === null || !$connection->belongsTo($probe->id())) {
            throw new ConnectionNotOwnedByProbe();
        }
    }

    private function extractConnectionId(Request $request): ?string
    {
        $content = $request->getContent();

        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded) || !isset($decoded['connectionId']) || !is_string($decoded['connectionId'])) {
            return null;
        }

        return $decoded['connectionId'];
    }
}
