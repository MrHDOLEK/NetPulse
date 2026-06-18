<?php

declare(strict_types=1);

namespace App\Measurement\Application\Action;

use App\Measurement\Application\PublicResult\PublicResult;
use App\Measurement\Application\PublicResult\PublicResultRepository;
use App\Measurement\Application\PublicResult\ResultNotFound;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function ucfirst;

final class PublicResultAction extends AbstractController
{
    public function __construct(
        private readonly PublicResultRepository $publicResults,
    ) {}

    #[Route(
        '/r/{shareToken}',
        name: 'public_result',
        methods: ['GET'],
        requirements: ['shareToken' => '[A-Za-z0-9_-]{43}'],
    )]
    public function show(string $shareToken): Response
    {
        try {
            $result = $this->publicResults->get($shareToken);
        } catch (ResultNotFound) {
            return new Response($this->renderView('public/result_not_found.html.twig'), Response::HTTP_NOT_FOUND);
        }

        return $this->render('public/result.html.twig', $this->present($result));
    }

    /**
     * @return array<string,mixed>
     */
    private function present(PublicResult $result): array
    {
        $completedAt = new DateTimeImmutable('@' . $result->completedAtUnix)->setTimezone(new DateTimeZone('UTC'));

        return [
            'downloadBits' => $result->downloadBits,
            'uploadBits' => $result->uploadBits,
            'pingSeconds' => $result->pingSeconds,
            'jitterSeconds' => $result->jitterSeconds,
            'lossRatio' => $result->lossRatio,
            'serverName' => $result->serverName,
            'serverLocation' => $result->serverLocation,
            'isp' => $result->isp,
            'completedAtUtc' => $completedAt->format('Y-m-d H:i:s') . ' UTC',
            'status' => $result->status->value,
            'statusLabel' => ucfirst($result->status->value),
            'healthy' => $result->healthy,
        ];
    }
}
