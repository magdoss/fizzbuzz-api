<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Dto\FizzBuzzQuery;
use App\Repository\RequestStatRepository;
use PHPUnit\Framework\TestCase;

final class RequestStatRepositoryTest extends TestCase
{
    public function testHashIsStableForTheSameRequest(): void
    {
        self::assertSame(
            RequestStatRepository::hash(new FizzBuzzQuery(3, 5, 100, 'fizz', 'buzz')),
            RequestStatRepository::hash(new FizzBuzzQuery(3, 5, 100, 'fizz', 'buzz')),
        );
    }

    public function testSwappedParametersAreDifferentRequests(): void
    {
        self::assertNotSame(
            RequestStatRepository::hash(new FizzBuzzQuery(3, 5, 100, 'fizz', 'buzz')),
            RequestStatRepository::hash(new FizzBuzzQuery(5, 3, 100, 'buzz', 'fizz')),
        );
    }

    public function testStringsCannotCollideThroughConcatenation(): void
    {
        self::assertNotSame(
            RequestStatRepository::hash(new FizzBuzzQuery(3, 5, 100, 'a|b', 'c')),
            RequestStatRepository::hash(new FizzBuzzQuery(3, 5, 100, 'a', 'b|c')),
        );
    }
}
