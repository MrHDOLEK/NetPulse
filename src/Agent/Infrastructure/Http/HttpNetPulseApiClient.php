<?php

declare(strict_types=1);

namespace App\Agent\Infrastructure\Http;

use App\Agent\Application\AgentTask;
use App\Agent\Application\DuePlan;
use App\Agent\Application\NetPulseApiClient;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_merge;
use function is_array;
use function is_int;
use function is_string;
use function rtrim;
use function sprintf;
use function substr;

#[AsAlias(NetPulseApiClient::class)]
final readonly class HttpNetPulseApiClient implements NetPulseApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(NETPULSE_API_URL)%')]
        private string $baseUrl,
        #[Autowire('%env(PROBE_ID)%')]
        private string $probeId,
        #[Autowire('%env(PROBE_TOKEN)%')]
        private string $probeToken,
    ) {}

    public function fetchDue(): DuePlan
    {
        $response = $this->httpClient->request('GET', $this->url('/due'), [
            'headers' => $this->headers(),
        ]);

        /** @var array<string,mixed> $payload */
        $payload = $response->toArray();

        return $this->toDuePlan($payload);
    }

    public function pushResult(string $connectionId, array $ooklaJson, bool $scheduled): void
    {
        $body = array_merge($ooklaJson, [
            'connectionId' => $connectionId,
            'scheduled' => $scheduled,
        ]);

        $response = $this->httpClient->request('POST', $this->url('/results'), [
            'headers' => $this->headers(),
            'json' => $body,
        ]);

        $status = $response->getStatusCode();

        if ($status !== Response::HTTP_CREATED) {
            throw new RuntimeException(sprintf(
                'Pushing measurement for connection %s failed: server responded HTTP %d %s',
                $connectionId,
                $status,
                substr($response->getContent(false), 0, 200),
            ));
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function toDuePlan(array $payload): DuePlan
    {
        $tasks = [];
        $rawTasks = $payload['tasks'] ?? [];

        if (is_array($rawTasks)) {
            foreach ($rawTasks as $rawTask) {
                if (!is_array($rawTask)) {
                    continue;
                }

                $connectionId = $rawTask['connectionId'] ?? null;
                $serverId = $rawTask['serverId'] ?? null;

                if (!is_string($connectionId)) {
                    continue;
                }

                $tasks[] = new AgentTask($connectionId, is_string($serverId) ? $serverId : null);
            }
        }

        $pollAfterSeconds = $payload['pollAfterSeconds'] ?? 0;

        return new DuePlan($tasks, is_int($pollAfterSeconds) ? $pollAfterSeconds : 0);
    }

    private function url(string $suffix): string
    {
        return rtrim($this->baseUrl, '/') . '/api/v1/probes/' . $this->probeId . $suffix;
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->probeToken,
            'Accept' => 'application/json',
        ];
    }
}
