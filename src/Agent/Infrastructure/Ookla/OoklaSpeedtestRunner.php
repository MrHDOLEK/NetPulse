<?php

declare(strict_types=1);

namespace App\Agent\Infrastructure\Ookla;

use App\Agent\Application\SpeedtestOutcome;
use App\Agent\Application\SpeedtestRunner;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Throwable;

use function array_is_list;
use function ctype_digit;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

#[AsAlias(SpeedtestRunner::class)]
final readonly class OoklaSpeedtestRunner implements SpeedtestRunner
{
    private const int TIMEOUT_SECONDS = 120;

    public function __construct(
        #[Autowire('%env(OOKLA_BINARY)%')]
        private string $binary,
    ) {}

    public function run(?string $serverId): SpeedtestOutcome
    {
        $process = new Process($this->buildCommand($serverId));
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->run();
        } catch (Throwable $exception) {
            return SpeedtestOutcome::failure($exception->getMessage());
        }

        if (!$process->isSuccessful()) {
            return SpeedtestOutcome::failure($this->describeFailure($process));
        }

        return $this->parse($process->getOutput());
    }

    /**
     * @return list<string>
     */
    private function buildCommand(?string $serverId): array
    {
        $command = [
            $this->binary,
            '--format=json',
            '--accept-license',
            '--accept-gdpr',
        ];

        if ($serverId !== null) {
            $command[] = ctype_digit($serverId) ? '--server-id=' . $serverId : '--host=' . $serverId;
        }

        return $command;
    }

    private function parse(string $output): SpeedtestOutcome
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            return SpeedtestOutcome::failure('invalid Ookla JSON: ' . $exception->getMessage());
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            return SpeedtestOutcome::failure('Ookla output was not a JSON object');
        }

        $payload = [];

        foreach ($decoded as $key => $value) {
            $payload[(string) $key] = $value;
        }

        return SpeedtestOutcome::success($payload);
    }

    private function describeFailure(Process $process): string
    {
        $stderr = trim($process->getErrorOutput());
        $message = 'speedtest exited with code ' . (string) $process->getExitCode();

        return $stderr === '' ? $message : $message . ': ' . $stderr;
    }
}
