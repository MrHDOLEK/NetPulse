<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent\Infrastructure\Ookla;

use App\Agent\Infrastructure\Ookla\OoklaSpeedtestRunner;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function array_map;
use function dirname;
use function explode;

final class OoklaSpeedtestRunnerTest extends TestCase
{
    public function testZeroExitWithValidJsonProducesASuccessOutcome(): void
    {
        $runner = new OoklaSpeedtestRunner(self::stub('ookla-success.sh'));

        $outcome = $runner->run(null);

        self::assertTrue($outcome->successful);
        $json = $outcome->toOoklaJson(null);
        self::assertSame('result', $json['type']);
        self::assertSame(12500000, $json['download']['bandwidth']);
    }

    public function testServerIdIsForwardedAndStillParsesOnSuccess(): void
    {
        $runner = new OoklaSpeedtestRunner(self::stub('ookla-success.sh'));

        $outcome = $runner->run('12345');

        self::assertTrue($outcome->successful);
    }

    public function testNullServerValueAddsNeitherServerIdNorHostFlag(): void
    {
        $command = $this->buildCommand(null);

        self::assertNotContains('--server-id', $this->flagNames($command));
        self::assertNotContains('--host', $this->flagNames($command));
    }

    public function testNumericServerValueTargetsServerIdNotHost(): void
    {
        $command = $this->buildCommand('12345');

        self::assertContains('--server-id=12345', $command);
        self::assertNotContains('--host', $this->flagNames($command));
    }

    public function testHostStyleServerValueTargetsHostNotServerId(): void
    {
        $command = $this->buildCommand('frankfurt.example.net:8080');

        self::assertContains('--host=frankfurt.example.net:8080', $command);
        self::assertNotContains('--server-id', $this->flagNames($command));
    }

    public function testNonZeroExitProducesAFailureOutcome(): void
    {
        $runner = new OoklaSpeedtestRunner(self::stub('ookla-fail.sh'));

        $outcome = $runner->run(null);

        self::assertFalse($outcome->successful);
        self::assertNotNull($outcome->errorMessage);

        $json = $outcome->toOoklaJson(null);
        self::assertSame('error', $json['type']);
    }

    public function testZeroExitWithInvalidJsonProducesAFailureOutcome(): void
    {
        $runner = new OoklaSpeedtestRunner(self::stub('ookla-garbage.sh'));

        $outcome = $runner->run(null);

        self::assertFalse($outcome->successful);
    }

    public function testMissingBinaryProducesAFailureOutcome(): void
    {
        $runner = new OoklaSpeedtestRunner('/nonexistent/path/to/speedtest-binary');

        $outcome = $runner->run(null);

        self::assertFalse($outcome->successful);
        self::assertNotNull($outcome->errorMessage);
    }

    private static function stub(string $name): string
    {
        return dirname(__DIR__, 4) . '/support/ookla/' . $name;
    }

    /**
     * @return list<string>
     */
    private function buildCommand(?string $serverId): array
    {
        $runner = new OoklaSpeedtestRunner(self::stub('ookla-success.sh'));
        $method = new ReflectionMethod($runner, 'buildCommand');

        /** @var list<string> $command */
        return $method->invoke($runner, $serverId);
    }

    /**
     * @param list<string> $command
     * @return list<string>
     */
    private function flagNames(array $command): array
    {
        return array_map(static fn(string $arg): string => explode('=', $arg, 2)[0], $command);
    }
}
