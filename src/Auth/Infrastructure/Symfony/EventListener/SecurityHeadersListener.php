<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Symfony\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function bin2hex;
use function implode;
use function is_string;
use function random_bytes;
use function sprintf;
use function str_contains;
use function str_starts_with;

final class SecurityHeadersListener
{
    public const string NONCE_ATTRIBUTE = 'csp_nonce';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->isExempt($request->getPathInfo())) {
            return;
        }

        $request->attributes->set(self::NONCE_ATTRIBUTE, bin2hex(random_bytes(16)));
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: 0)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->isExempt($request->getPathInfo())) {
            return;
        }

        $response = $event->getResponse();

        if (!$this->isHtml($response)) {
            return;
        }

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $nonce = $request->attributes->get(self::NONCE_ATTRIBUTE);
        $scriptSrc = is_string($nonce) ? sprintf("script-src 'self' 'nonce-%s'", $nonce) : "script-src 'self'";

        return implode('; ', [
            "default-src 'self'",
            $scriptSrc,

            "style-src 'self' 'unsafe-inline'",

            "img-src 'self' data:",

            "font-src 'self'",

            "connect-src 'self'",
            "base-uri 'self'",

            "frame-ancestors 'none'",
            "object-src 'none'",
        ]);
    }

    private function isExempt(string $path): bool
    {
        return str_starts_with($path, '/api') || $path === '/metrics';
    }

    private function isHtml(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }
}
