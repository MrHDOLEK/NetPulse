<?php

declare(strict_types=1);

namespace App\Probe\Application\Action;

use App\Connection\Domain\ConnectionRepository;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeRepository;
use DateTimeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ProbeListAction extends AbstractController
{
    public function __construct(
        private readonly ProbeRepository $probes,
        private readonly ConnectionRepository $connections,
    ) {}

    #[Route('/settings/probes', name: 'settings_probes', methods: ['GET'])]
    public function __invoke(): Response
    {
        $probes = [];

        foreach ($this->probes->all() as $probe) {
            $probes[] = $this->toRow($probe);
        }

        return $this->render('settings/probes/index.html.twig', [
            'probes' => $probes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Probe $probe): array
    {
        return [
            'id' => $probe->id()->toString(),
            'name' => $probe->name(),
            'enabled' => $probe->isEnabled(),
            'labels' => $probe->labels()->all(),
            'createdAt' => $probe->createdAt()->format(DateTimeInterface::ATOM),
            'connectionCount' => $this->connections->byProbe($probe->id())->count(),
        ];
    }
}
