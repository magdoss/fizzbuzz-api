<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\LimitCap;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class FizzBuzzQuery
{
    public function __construct(
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(propertyPath: 'limit', message: 'This value should be less than or equal to limit ({{ compared_value }}).')]
        public int $int1,
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(propertyPath: 'limit', message: 'This value should be less than or equal to limit ({{ compared_value }}).')]
        public int $int2,
        #[Assert\Positive]
        #[LimitCap]
        public int $limit,
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $str1,
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $str2,
    ) {
    }
}
