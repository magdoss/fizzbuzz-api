<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\FizzBuzzQuery;
use App\Exception\StatsUnavailableException;
use App\Repository\RequestStatRepository;
use App\Response\StreamedJsonArrayResponse;
use App\Service\FizzBuzzGenerator;
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
