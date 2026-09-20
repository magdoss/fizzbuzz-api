<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\FizzBuzzQuery;
use App\Exception\StatsUnavailableException;
use App\Repository\RequestStatRepository;
use App\Response\StreamedJsonArrayResponse;
use App\Service\FizzBuzzGenerator;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

final class FizzBuzzController
{
    public function __construct(
        private FizzBuzzGenerator $generator,
        private RequestStatRepository $stats,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/fizzbuzz', methods: ['GET'])]
    #[OA\Get(
        summary: 'Numbers from 1 to limit, multiples of int1 and int2 replaced by str1 and str2.',
        parameters: [
            new OA\Parameter(name: 'int1', in: 'query', description: 'Multiples of int1 become str1. Cannot exceed limit.', example: 3),
            new OA\Parameter(name: 'int2', in: 'query', description: 'Multiples of int2 become str2. Cannot exceed limit.', example: 5),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Last number of the list. Capped by FIZZBUZZ_MAX_LIMIT, 100000 by default.', example: 15),
            new OA\Parameter(name: 'str1', in: 'query', description: 'Replaces the multiples of int1.', example: 'fizz'),
            new OA\Parameter(name: 'str2', in: 'query', description: 'Replaces the multiples of int2. Multiples of both become str1 followed by str2.', example: 'buzz'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The list, streamed as a single JSON array of strings.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['1', '2', 'fizz', '4', 'buzz', 'fizz', '7', '8', 'fizz', 'buzz', '11', 'fizz', '13', '14', 'fizzbuzz'],
                ),
            ),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 503, ref: '#/components/responses/ServiceUnavailable'),
        ],
    )]
    public function __invoke(
        #[MapQueryString(
            serializationContext: [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false],
            validationFailedStatusCode: 400,
        )]
        FizzBuzzQuery $query,
    ): StreamedJsonArrayResponse {
        try {
            $this->stats->record($query);
        } catch (StatsUnavailableException $e) {
            $this->logger->error('The request could not be counted in the statistics.', ['exception' => $e]);
        }

        return new StreamedJsonArrayResponse($this->generator->generate($query));
    }
}
