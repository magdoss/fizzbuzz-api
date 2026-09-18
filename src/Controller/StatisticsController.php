<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\RequestStatRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatisticsController
{
    public function __construct(
        private RequestStatRepository $stats,
    ) {
    }

    #[Route('/statistics', methods: ['GET'])]
    #[OA\Get(
        summary: 'Parameters of the most used /fizzbuzz request, with its number of hits.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'The most used request. When several share the top count, the one seen first. request is null and hits is 0 until a request is recorded.',
                content: new OA\JsonContent(
                    required: ['request', 'hits'],
                    properties: [
                        new OA\Property(
                            property: 'request',
                            nullable: true,
                            required: ['int1', 'int2', 'limit', 'str1', 'str2'],
                            properties: [
                                new OA\Property(property: 'int1', type: 'integer', example: 3),
                                new OA\Property(property: 'int2', type: 'integer', example: 5),
                                new OA\Property(property: 'limit', type: 'integer', example: 100),
                                new OA\Property(property: 'str1', type: 'string', example: 'fizz'),
                                new OA\Property(property: 'str2', type: 'string', example: 'buzz'),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(property: 'hits', type: 'integer', example: 42),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 503, ref: '#/components/responses/ServiceUnavailable'),
        ],
    )]
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
