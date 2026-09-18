<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\FizzBuzzQuery;
use App\Response\StreamedJsonArrayResponse;
use App\Service\FizzBuzzGenerator;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

final class FizzBuzzController
{
    public function __construct(
        private FizzBuzzGenerator $generator,
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
        return new StreamedJsonArrayResponse($this->generator->generate($query));
    }
}
