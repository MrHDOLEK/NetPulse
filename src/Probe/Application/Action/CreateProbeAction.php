<?php

declare(strict_types=1);

namespace App\Probe\Application\Action;

use App\Probe\Application\Command\CreateProbe\CreateProbeCommand;
use App\Probe\Application\Command\CreateProbe\ProbeCreated;
use App\Shared\Domain\ValueObject\Labels;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function count;
use function explode;
use function is_array;
use function is_string;
use function trim;

#[IsGranted("ROLE_ADMIN")]
final class CreateProbeAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = "probe-create";

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {}

    #[Route("/settings/probes", name: "settings_probes_create", methods: ["POST"])]
    public function __invoke(Request $request): Response
    {
        $token = $request->headers->get("X-CSRF-Token");

        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(["error" => "Invalid CSRF token"], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? $body : [];

        $name = is_string($body["name"] ?? null) ? trim($body["name"]) : "";

        if ($name === "") {
            return new JsonResponse(["error" => "A probe name is required"], Response::HTTP_BAD_REQUEST);
        }

        $envelope = $this->commandBus->dispatch(
            new CreateProbeCommand($name, Labels::fromArray($this->parseLabels($body["labels"] ?? null))),
        );

        $result = $envelope->last(HandledStamp::class)?->getResult();

        if (!$result instanceof ProbeCreated) {
            return new JsonResponse(["error" => "Probe creation failed"], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = new JsonResponse([
            "id" => $result->probeId->toString(),
            "token" => $result->plaintextToken,
        ], Response::HTTP_CREATED);
        $response->headers->set("Cache-Control", "no-cache");

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function parseLabels(mixed $raw): array
    {
        $labels = [];

        if (is_string($raw)) {
            foreach (explode(",", $raw) as $pair) {
                $parts = explode("=", $pair, 2);

                if (count($parts) === 2 && trim($parts[0]) !== "") {
                    $labels[trim($parts[0])] = trim($parts[1]);
                }
            }

            return $labels;
        }

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (is_string($key) && is_string($value) && trim($key) !== "") {
                    $labels[trim($key)] = trim($value);
                }
            }
        }

        return $labels;
    }
}
