<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Infrastructure\Symfony\Listener;

use App\Metrics\Infrastructure\Config\PrometheusConfig;
use App\Metrics\Infrastructure\Symfony\Listener\PrometheusAllowedIpsListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PrometheusAllowedIpsListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, string, string, string, ?int}>
     */
    public static function provideCases(): iterable
    {
        yield "metrics disabled returns 404" => [false, "", "/metrics", "127.0.0.1", Response::HTTP_NOT_FOUND];
        yield "client ip outside allowlist returns 403" => [true, "10.0.0.0/8", "/metrics", "203.0.113.5", Response::HTTP_FORBIDDEN];
        yield "client ip inside allowlist passes through" => [true, "10.0.0.0/8", "/metrics", "10.1.2.3", null];
        yield "empty allowlist allows any ip" => [true, "", "/metrics", "203.0.113.5", null];
        yield "non-metrics path is ignored" => [false, "10.0.0.0/8", "/api/v1", "203.0.113.5", null];
    }

    #[DataProvider("provideCases")]
    public function testEnforcesAllowlist(
        bool $metricsEnabled,
        string $allowedIpsRaw,
        string $path,
        string $clientIp,
        ?int $expectedStatus,
    ): void {
        $listener = new PrometheusAllowedIpsListener(
            new PrometheusConfig($metricsEnabled, $allowedIpsRaw, 3600),
        );

        $event = $this->event($path, $clientIp);
        $listener->onKernelRequest($event);

        if ($expectedStatus === null) {
            self::assertNull($event->getResponse());

            return;
        }

        self::assertNotNull($event->getResponse());
        self::assertSame($expectedStatus, $event->getResponse()->getStatusCode());
    }

    private function event(string $path, string $clientIp): RequestEvent
    {
        $request = Request::create($path, "GET", server: ["REMOTE_ADDR" => $clientIp]);

        return new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function kernel(): HttpKernelInterface
    {
        return new class() implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
