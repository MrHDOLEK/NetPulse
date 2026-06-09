<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Symfony\EventListener;

use App\Auth\Domain\UserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function str_starts_with;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 48)]
final readonly class FirstRunSetupListener
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if ($this->isExempt($path)) {
            return;
        }

        if ($this->users->count() !== 0) {
            return;
        }

        $event->setResponse(new RedirectResponse("/setup"));
    }

    private function isExempt(string $path): bool
    {
        return str_starts_with($path, "/api")
            || $path === "/metrics"
            || str_starts_with($path, "/assets")
            || str_starts_with($path, "/r/")
            || str_starts_with($path, "/2fa")
            || $path === "/setup"
            || $path === "/login"
            || str_starts_with($path, "/login/oidc")
            || $path === "/logout";
    }
}
