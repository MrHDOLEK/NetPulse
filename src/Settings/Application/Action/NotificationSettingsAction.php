<?php

declare(strict_types=1);

namespace App\Settings\Application\Action;

use App\Notification\Application\NotificationTester;
use App\Settings\Application\SaveSettings\SaveSettingsCommand;
use App\Settings\Application\SettingsException;
use App\Settings\Application\SettingsReader;
use App\Settings\Domain\SettingKey;
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
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function trim;

#[IsGranted('ROLE_ADMIN')]
final class NotificationSettingsAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'settings-notifications';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly SettingsReader $settings,
        private readonly SettingsSecretEncryptor $encryptor,
        private readonly NotificationTester $tester,
    ) {}

    #[Route('/settings/notifications', name: 'settings_notifications', methods: ['GET'])]
    public function index(): Response
    {
        $channels = $this->settings->getString(SettingKey::NotifyChannels);

        return $this->render('settings/notifications/index.html.twig', [
            'enabled' => $this->settings->getBool(SettingKey::NotifyEnabled),
            'threshold' => $this->settings->getString(SettingKey::NotifyThreshold),
            'emailEnabled' => $this->hasChannel($channels, 'email'),
            'chatEnabled' => $this->hasChannel($channels, 'chat'),
            'webhookEnabled' => $this->hasChannel($channels, 'webhook'),
            'emailTo' => $this->settings->getString(SettingKey::NotifyEmailTo),
            'emailDsnSet' => $this->isSet(SettingKey::NotifyEmailDsn),
            'chatDsnSet' => $this->isSet(SettingKey::NotifyChatDsn),
            'webhookUrlSet' => $this->isSet(SettingKey::NotifyWebhookUrl),
            'canEncrypt' => $this->encryptor->canEncrypt(),
        ]);
    }

    #[Route('/settings/notifications', name: 'settings_notifications_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (!$this->csrfValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? $body : [];

        $values = [
            SettingKey::NotifyEnabled->value => $this->bool($body, 'enabled') ? '1' : '0',
            SettingKey::NotifyThreshold->value => $this->str($body, 'threshold'),
            SettingKey::NotifyChannels->value => $this->channels($body),
            SettingKey::NotifyEmailTo->value => $this->str($body, 'emailTo'),

            SettingKey::NotifyEmailDsn->value => $this->secret($body, 'emailDsn'),
            SettingKey::NotifyChatDsn->value => $this->secret($body, 'chatDsn'),
            SettingKey::NotifyWebhookUrl->value => $this->secret($body, 'webhookUrl'),
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
            'emailDsnSet' => $this->isSet(SettingKey::NotifyEmailDsn),
            'chatDsnSet' => $this->isSet(SettingKey::NotifyChatDsn),
            'webhookUrlSet' => $this->isSet(SettingKey::NotifyWebhookUrl),
        ]);
    }

    #[Route('/settings/notifications/test', name: 'settings_notifications_test', methods: ['POST'])]
    public function test(Request $request): Response
    {
        if (!$this->csrfValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $results = $this->tester->test();

        if ($results === []) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'No channels are enabled — enable at least one channel and save first.',
                'results' => [],
            ]);
        }

        $ok = in_array('sent', $results, true);

        return new JsonResponse([
            'ok' => $ok,
            'message' => $ok
                ? 'Test dispatched — check your channels.'
                : 'Nothing was delivered. See the per-channel results below.',
            'results' => $results,
        ]);
    }

    private function csrfValid(Request $request): bool
    {
        $token = $request->headers->get('X-CSRF-Token');

        return is_string($token) && $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function channels(array $body): string
    {
        $active = [];

        if ($this->bool($body, 'emailEnabled')) {
            $active[] = 'email';
        }

        if ($this->bool($body, 'chatEnabled')) {
            $active[] = 'chat';
        }

        if ($this->bool($body, 'webhookEnabled')) {
            $active[] = 'webhook';
        }

        return implode(',', $active);
    }

    private function hasChannel(string $csv, string $channel): bool
    {
        foreach (explode(',', $csv) as $item) {
            if (trim($item) === $channel) {
                return true;
            }
        }

        return false;
    }

    private function isSet(SettingKey $key): bool
    {
        $value = trim($this->settings->getString($key));

        return $value !== '' && $value !== 'null://null';
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function secret(array $body, string $key): ?string
    {
        if (!array_key_exists($key, $body)) {
            return null;
        }

        $value = $body[$key];

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
