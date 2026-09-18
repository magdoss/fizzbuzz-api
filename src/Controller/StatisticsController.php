<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\RequestStatRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatisticsController
{
    public function __construct(
        private RequestStatRepository $stats,
    ) {
    }

    #[Route('/statistics', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $mostUsed = $this->stats->mostUsed();

        $response = new JsonResponse([
            'request' => $mostUsed?->request(),
            'hits' => $mostUsed?->hits() ?? 0,
        ]);

        return $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
