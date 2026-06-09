<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent\Infrastructure\Http;

use App\Agent\Infrastructure\Http\HttpNetPulseApiClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function strtolower;

final class HttpNetPulseApiClientTest extends TestCase
{
    private const string BASE_URL = "http://app:8080";
    private const string PROBE_ID = "44444444-4444-7444-8444-444444444444";
    private const string TOKEN = "secret-probe-token";
    private const string CONN = "55555555-5555-7555-8555-555555555555";

    public function testFetchDueGetsTheDueUrlWithBearerAndMapsTheResponse(): void
    {
        $captured = [];

        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured["method"] = $method;
            $captured["url"] = $url;
            $captured["headers"] = $options["normalized_headers"] ?? $options["headers"] ?? [];

            return new MockResponse((string)json_encode([
                "tasks" => [
                    ["connectionId" => self::CONN, "serverId" => "999"],
                    ["connectionId" => "66666666-6666-7666-8666-666666666666", "serverId" => null],
                ],
                "pollAfterSeconds" => 45,
            ]), ["http_code" => 200]);
        });

        $plan = $this->client($mock)->fetchDue();

        self::assertSame("GET", $captured["method"]);
        self::assertSame(self::BASE_URL . "/api/v1/probes/" . self::PROBE_ID . "/due", $captured["url"]);
        self::assertStringContainsString("authorization: bearer " . self::TOKEN, $this->headerBlob($captured["headers"]));

        self::assertCount(2, $plan->tasks);
        self::assertSame(self::CONN, $plan->tasks[0]->connectionId);
        self::assertSame("999", $plan->tasks[0]->serverId);
        self::assertNull($plan->tasks[1]->serverId);
        self::assertSame(45, $plan->pollAfterSeconds);
    }

    public function testPushResultPostsOoklaJsonMergedWithConnectionIdAndScheduled(): void
    {
        $captured = [];

        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured["method"] = $method;
            $captured["url"] = $url;
            $captured["headers"] = $options["normalized_headers"] ?? $options["headers"] ?? [];
            $captured["body"] = $options["body"] ?? "";

            return new MockResponse("", ["http_code" => 201]);
        });

        $this->client($mock)->pushResult(
            self::CONN,
            ["type" => "result", "ping" => ["latency" => 9.0], "connectionId" => "should-be-overwritten"],
            true,
        );

        self::assertSame("POST", $captured["method"]);
        self::assertSame(self::BASE_URL . "/api/v1/probes/" . self::PROBE_ID . "/results", $captured["url"]);
        self::assertStringContainsString("authorization: bearer " . self::TOKEN, $this->headerBlob($captured["headers"]));

        /** @var array<string,mixed> $body */
        $body = json_decode(is_string($captured["body"]) ? $captured["body"] : "", true);

        self::assertSame(self::CONN, $body["connectionId"]);
        self::assertTrue($body["scheduled"]);
        self::assertSame("result", $body["type"]);
        self::assertSame(["latency" => 9.0], $body["ping"]);
    }

    public function testPushResultThrowsWhenServerRejectsTheMeasurement(): void
    {
        $mock = new MockHttpClient(static fn(): MockResponse => new MockResponse(
            (string)json_encode(["errors" => ["general" => "VALIDATION.INVALID_PAYLOAD"]]),
            ["http_code" => 400],
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/HTTP 400/");

        $this->client($mock)->pushResult(self::CONN, ["type" => "result"], true);
    }

    public function testFetchDueToleratesAMissingTasksKey(): void
    {
        $mock = new MockHttpClient(static fn(): MockResponse => new MockResponse(
            (string)json_encode(["pollAfterSeconds" => 10]),
            ["http_code" => 200],
        ));

        $plan = $this->client($mock)->fetchDue();

        self::assertSame([], $plan->tasks);
        self::assertSame(10, $plan->pollAfterSeconds);
    }

    private function client(MockHttpClient $http): HttpNetPulseApiClient
    {
        return new HttpNetPulseApiClient($http, self::BASE_URL, self::PROBE_ID, self::TOKEN);
    }

    /**
     * @param array<mixed> $headers
     */
    private function headerBlob(array $headers): string
    {
        $flat = [];

        foreach ($headers as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $flat[] = is_string($key) ? "{$key}: {$item}" : (string)$item;
                }

                continue;
            }

            $flat[] = is_string($key) ? "{$key}: {$value}" : (string)$value;
        }

        return strtolower(implode("\n", $flat));
    }
}
