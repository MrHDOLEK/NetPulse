<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Ookla;

use App\Dashboard\Application\OoklaServer;
use App\Dashboard\Application\OoklaServerCatalog;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

use function is_array;
use function is_int;
use function is_string;
use function json_decode;

#[AsAlias(OoklaServerCatalog::class)]
final class CliOoklaServerCatalog implements OoklaServerCatalog
{
    private const string CACHE_KEY = 'dashboard.ookla_server_catalog';

    public function __construct(
        #[Autowire('%env(OOKLA_BINARY)%')]
        private readonly string $binary,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function servers(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $servers = $this->fetch();
            $item->expiresAfter($servers === [] ? 60 : 3600);

            return $servers;
        });
    }

    /**
     * @return list<OoklaServer>
     */
    private function fetch(): array
    {
        $process = new Process([$this->binary, '--servers', '--format=json', '--accept-license', '--accept-gdpr']);
        $process->setTimeout(15.0);

        try {
            $process->run();
        } catch (Throwable $exception) {
            $this->logger->warning('ookla --servers failed to run', ['error' => $exception->getMessage()]);

            return [];
        }

        if (!$process->isSuccessful()) {
            $this->logger->warning('ookla --servers exited non-zero', ['stderr' => $process->getErrorOutput()]);

            return [];
        }

        $decoded = json_decode($process->getOutput(), true);

        if (!is_array($decoded) || !isset($decoded['servers']) || !is_array($decoded['servers'])) {
            return [];
        }

        $servers = [];

        foreach ($decoded['servers'] as $entry) {
            if (!is_array($entry) || !isset($entry['id']) || !is_int($entry['id'])) {
                continue;
            }

            $servers[] = new OoklaServer(
                $entry['id'],
                is_string($entry['name'] ?? null) ? $entry['name'] : '',
                is_string($entry['location'] ?? null) ? $entry['location'] : '',
                is_string($entry['host'] ?? null) ? $entry['host'] : '',
            );
        }

        return $servers;
    }
}
