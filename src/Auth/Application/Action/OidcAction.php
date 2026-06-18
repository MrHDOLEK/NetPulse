<?php

declare(strict_types=1);

namespace App\Auth\Application\Action;

use App\Auth\Application\Oidc\OidcProvider;
use App\Auth\Infrastructure\Oidc\OidcConfig;
use App\Auth\Infrastructure\Symfony\Security\OidcAuthenticator;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function base64_encode;
use function bin2hex;
use function random_bytes;
use function rtrim;
use function strtr;

final class OidcAction extends AbstractController
{
    public function __construct(
        private readonly OidcConfig $config,
        private readonly OidcProvider $provider,
    ) {}

    #[Route('/login/oidc/start', name: 'oidc_start', methods: ['GET'])]
    public function start(Request $request): RedirectResponse
    {
        if (!$this->config->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $state = bin2hex(random_bytes(16));
        $codeVerifier = $this->base64Url(random_bytes(32));
        $nonce = bin2hex(random_bytes(16));

        $session = $request->getSession();
        $session->set(OidcAuthenticator::SESSION_STATE, $state);
        $session->set(OidcAuthenticator::SESSION_VERIFIER, $codeVerifier);
        $session->set(OidcAuthenticator::SESSION_NONCE, $nonce);

        return new RedirectResponse($this->provider->authorizationUrl($state, $codeVerifier, $nonce));
    }

    #[Route('/login/oidc/callback', name: 'oidc_callback', methods: ['GET'])]
    public function callback(): Response
    {
        throw new LogicException('The OIDC callback must be handled by OidcAuthenticator.');
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
