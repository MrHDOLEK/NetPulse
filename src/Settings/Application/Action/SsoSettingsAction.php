<?php

declare(strict_types=1);

namespace App\Settings\Application\Action;

use App\Settings\Application\SaveSettings\SaveSettingsCommand;
use App\Settings\Application\SettingsException;
use App\Settings\Application\SettingsReader;
use App\Settings\Domain\SettingKey;
use App\Settings\Infrastructure\Oidc\OidcDiscoveryProbe;
use App\Settings\Infrastructure\Security\SettingsSecretEncryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;
use function trim;

#[IsGranted('ROLE_ADMIN')]
final class SsoSettingsAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'settings-sso';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly SettingsReader $settings,
        private readonly SettingsSecretEncryptor $encryptor,
        private readonly OidcDiscoveryProbe $discoveryProbe,
    ) {}

    #[Route('/settings/sso', name: 'settings_sso', methods: ['GET'])]
    public function index(): Response
    {
        $secret = $this->settings->getString(SettingKey::OidcClientSecret);

        return $this->render('settings/sso/index.html.twig', [
            'enabled' => $this->settings->getBool(SettingKey::OidcEnabled),
            'name' => $this->settings->getString(SettingKey::OidcName),
            'clientId' => $this->settings->getString(SettingKey::OidcClientId),
            'authorizationUrl' => $this->settings->getString(SettingKey::OidcAuthorizationUrl),
            'tokenUrl' => $this->settings->getString(SettingKey::OidcTokenUrl),
            'userInfoUrl' => $this->settings->getString(SettingKey::OidcUserInfoUrl),
            'redirectUrl' => $this->settings->getString(SettingKey::OidcRedirectUrl),
            'scopes' => $this->settings->getString(SettingKey::OidcScopes),
            'secretIsSet' => $secret !== '',
            'canEncrypt' => $this->encryptor->canEncrypt(),
        ]);
    }

    #[Route('/settings/sso', name: 'settings_sso_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (!$this->csrfValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? $body : [];

        $values = [
            SettingKey::OidcEnabled->value => $this->bool($body, 'enabled') ? '1' : '0',
            SettingKey::OidcName->value => $this->str($body, 'name'),
            SettingKey::OidcClientId->value => $this->str($body, 'clientId'),
            SettingKey::OidcAuthorizationUrl->value => $this->str($body, 'authorizationUrl'),
            SettingKey::OidcTokenUrl->value => $this->str($body, 'tokenUrl'),
            SettingKey::OidcUserInfoUrl->value => $this->str($body, 'userInfoUrl'),
            SettingKey::OidcRedirectUrl->value => $this->str($body, 'redirectUrl'),
            SettingKey::OidcScopes->value => $this->str($body, 'scopes'),

            SettingKey::OidcClientSecret->value => $this->secret($body),
        ];

        try {
            $this->commandBus->dispatch(new SaveSettingsCommand($values));
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof SettingsException) {
                return new JsonResponse(['error' => $cause->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            throw $exception;
        }

        return new JsonResponse([
            'saved' => true,
            'secretIsSet' => $this->settings->getString(SettingKey::OidcClientSecret) !== '',
        ]);
    }

    #[Route('/settings/sso/test', name: 'settings_sso_test', methods: ['POST'])]
    public function test(Request $request): Response
    {
        if (!$this->csrfValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? $body : [];

        $candidate = $this->str($body, 'discoveryUrl');

        if ($candidate === '') {
            $candidate = $this->settings->getString(SettingKey::OidcAuthorizationUrl);
        }

        $result = $this->discoveryProbe->probe($candidate);

        return new JsonResponse(['ok' => $result->ok, 'message' => $result->message]);
    }

    private function csrfValid(Request $request): bool
    {
        $token = $request->headers->get('X-CSRF-Token');

        return is_string($token) && $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function secret(array $body): ?string
    {
        if (!array_key_exists('clientSecret', $body)) {
            return null;
        }

        $value = $body['clientSecret'];

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function str(array $body, string $key): string
    {
        $value = $body[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function bool(array $body, string $key): bool
    {
        return ($body[$key] ?? false) === true;
    }
}
