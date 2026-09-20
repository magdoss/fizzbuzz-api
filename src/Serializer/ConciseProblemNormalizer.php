<?php

declare(strict_types=1);

namespace App\Serializer;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Keeps only what a client needs to fix its request: the field and the message.
 */
#[AsDecorator('serializer.normalizer.problem')]
final class ConciseProblemNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    public function __construct(
        #[AutowireDecorated]
        private NormalizerInterface&SerializerAwareInterface $inner,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $problem = $this->inner->normalize($data, $format, $context);
        \assert(\is_array($problem));

        if (\is_array($problem['violations'] ?? null)) {
            $violations = [];
            foreach ($problem['violations'] as $violation) {
                \assert(\is_array($violation));
                $violations[] = ['propertyPath' => $violation['propertyPath'], 'message' => $violation['title']];
            }
            $problem['violations'] = $violations;
        }

        return $problem;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->inner->setSerializer($serializer);
    }
}
