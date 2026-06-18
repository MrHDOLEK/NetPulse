<?php

declare(strict_types=1);

namespace App\Dev\Infrastructure\Symfony\Console;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Measurement\Application\Ookla\OoklaBandwidth;
use App\Measurement\Application\Ookla\OoklaLatency;
use App\Measurement\Application\Ookla\OoklaPing;
use App\Measurement\Application\Ookla\OoklaResult;
use App\Measurement\Application\Ookla\OoklaResultMapper;
use App\Measurement\Application\Ookla\OoklaResultMeta;
use App\Measurement\Application\Ookla\OoklaServer;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\Service\HealthEvaluator;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Probe\Domain\ValueObject\ProbeToken;
use App\Shared\Application\Service\IdGeneratorInterface;
use App\Shared\Domain\ValueObject\Labels;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function is_numeric;
use function sprintf;

#[AsCommand(
    name: 'app:dev:seed',
    description: 'Seed one probe, two connections and synthetic measurements for local dev.',
)]
final class SeedCommand extends Command
{
    private const array SERVERS = [
        [
            'id' => '12345',
            'name' => 'Speedtest Frankfurt',
            'location' => 'Frankfurt, DE',
            'host' => 'fra.speedtest.example:8080',
            'isp' => 'DE-Telekom',
        ],
        [
            'id' => '23456',
            'name' => 'Speedtest Warsaw',
            'location' => 'Warsaw, PL',
            'host' => 'waw.speedtest.example:8080',
            'isp' => 'Orange-PL',
        ],
        [
            'id' => '34567',
            'name' => 'Speedtest Amsterdam',
            'location' => 'Amsterdam, NL',
            'host' => 'ams.speedtest.example:8080',
            'isp' => 'KPN-NL',
        ],
    ];

    public function __construct(
        private readonly IdGeneratorInterface $idGenerator,
        private readonly ProbeTokenHasher $probeTokenHasher,
        private readonly ProbeRepository $probeRepository,
        private readonly ConnectionRepository $connectionRepository,
        private readonly MeasurementRepository $measurementRepository,
        private readonly ClockInterface $clock,
        private readonly OoklaResultMapper $mapper,
        private readonly HealthEvaluator $healthEvaluator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'measurements',
            'm',
            InputOption::VALUE_REQUIRED,
            'Number of synthetic measurements to generate per connection',
            '20',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $measurementsOption = $input->getOption('measurements');
        $measurementsPerConnection = is_numeric($measurementsOption) ? (int) $measurementsOption : 20;

        if ($measurementsPerConnection < 1) {
            $measurementsPerConnection = 1;
        }

        $token = ProbeToken::generate();
        $now = $this->clock->now();

        $probe = new Probe(
            new ProbeId($this->idGenerator->generate()->toString()),
            'dev-probe',
            Labels::fromArray(['site' => 'home', 'link' => 'wan1']),
            $this->probeTokenHasher->hash($token->toString()),
            true,
            $now,
        );
        $this->probeRepository->save($probe);

        $primary = new Connection(
            new ConnectionId($this->idGenerator->generate()->toString()),
            $probe->id(),
            'Fiber WAN1',
            'DE-Telekom',
            new ExpectedSpeed(1_000_000_000, 500_000_000),
            ConnectionColor::Primary,
            Labels::fromArray(['link' => 'wan1']),
            ServerPool::fromList('12345', '23456', '34567'),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
        $secondary = new Connection(
            new ConnectionId($this->idGenerator->generate()->toString()),
            $probe->id(),
            'LTE WAN2',
            'Orange-PL',
            new ExpectedSpeed(300_000_000, 50_000_000),
            ConnectionColor::Violet,
            Labels::fromArray(['link' => 'wan2']),
            ServerPool::fromList('23456', '34567'),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
        $this->connectionRepository->save($primary);
        $this->connectionRepository->save($secondary);

        $connections = [$primary, $secondary];
        $totalMeasurements = 0;

        foreach ($connections as $connectionIndex => $connection) {
            $degradedConnection = $connectionIndex === 1;

            for ($run = 0; $run < $measurementsPerConnection; ++$run) {
                $server = self::SERVERS[($run + $connectionIndex) % count(self::SERVERS)];

                $isFailed = $run !== 0 && ($run % 4) === 3;

                $isDegraded = $degradedConnection && !$isFailed && $run < 2;

                $completedAt = $now->modify(sprintf('-%d minutes', $run * 15));

                $result = $this->buildOoklaResult($server, $isFailed, $isDegraded, $run);

                $measurement = $this->mapper->toMeasurement(
                    new MeasurementId($this->idGenerator->generate()->toString()),
                    $probe->id(),
                    $connection->id(),
                    $result,
                    true,
                    $completedAt,
                    [],
                );

                $verdict = $this->healthEvaluator->evaluate(
                    $measurement,
                    $connection->thresholds(),
                    $connection->expected(),
                );
                $measurement->markHealth($verdict->isHealthy());

                $this->measurementRepository->save($measurement);
                ++$totalMeasurements;
            }
        }

        $io->success('Dev seed complete.');
        $io->writeln(sprintf('Probe id: %s', $probe->id()->toString()));
        $io->writeln(sprintf('Probe token: %s', $token->toString()));
        $io->writeln(sprintf('Connections: %d', count($connections)));
        $io->writeln(sprintf('Measurements: %d', $totalMeasurements));

        return Command::SUCCESS;
    }

    /**
     * @param array{id:string,name:string,location:string,host:string,isp:string} $server
     */
    private function buildOoklaResult(array $server, bool $isFailed, bool $isDegraded, int $run): OoklaResult
    {
        $serverBlock = new OoklaServer(
            id: (int) $server['id'],
            name: $server['name'],
            location: $server['location'],
            host: $server['host'],
        );

        if ($isFailed) {
            return new OoklaResult(type: 'result', server: $serverBlock, isp: $server['isp']);
        }

        $downloadBandwidth = $isDegraded ? 15_000_000 : 110_000_000 + (($run % 5) * 3_000_000);
        $uploadBandwidth = $isDegraded ? 3_000_000 : 55_000_000 + (($run % 5) * 1_500_000);
        $ping = 8.0 + (($run % 7) * 1.5);

        return new OoklaResult(
            type: 'result',
            ping: new OoklaPing(latency: $ping, low: $ping - 1.2, high: $ping + 2.4, jitter: 0.6),
            download: new OoklaBandwidth(
                bandwidth: $downloadBandwidth,
                bytes: $downloadBandwidth * 5,
                elapsed: 5000,
                latency: new OoklaLatency(iqm: $ping + 1.1, low: $ping, high: $ping + 4.0, jitter: 0.9),
            ),
            upload: new OoklaBandwidth(
                bandwidth: $uploadBandwidth,
                bytes: $uploadBandwidth * 5,
                elapsed: 5000,
                latency: new OoklaLatency(iqm: $ping + 1.4, low: $ping, high: $ping + 5.0, jitter: 1.1),
            ),
            server: $serverBlock,
            result: new OoklaResultMeta(
                id: sprintf('res-%s-%d', $server['id'], $run),
                url: sprintf('https://www.speedtest.net/result/c/res-%s-%d', $server['id'], $run),
            ),
            packetLoss: ($run % 6) === 0 ? 0.5 : 0.0,
            isp: $server['isp'],
        );
    }
}
