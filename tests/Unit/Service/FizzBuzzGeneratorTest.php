<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\FizzBuzzQuery;
use App\Service\FizzBuzzGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FizzBuzzGeneratorTest extends TestCase
{
    /**
     * @return iterable<string, array{FizzBuzzQuery, string}>
     */
    public static function cases(): iterable
    {
        yield 'example from the exercise' => [
            new FizzBuzzQuery(3, 5, 15, 'fizz', 'buzz'),
            '1,2,fizz,4,buzz,fizz,7,8,fizz,buzz,11,fizz,13,14,fizzbuzz',
        ];
        yield 'int1 greater than int2 keeps str1 first' => [
            new FizzBuzzQuery(5, 3, 15, 'buzz', 'fizz'),
            '1,2,fizz,4,buzz,fizz,7,8,fizz,buzz,11,fizz,13,14,buzzfizz',
        ];
        yield 'common multiple is the lcm, not the product' => [
            new FizzBuzzQuery(4, 6, 12, 'a', 'b'),
            '1,2,3,a,5,b,7,a,9,10,11,ab',
        ];
        yield 'int1 multiple of int2 never yields str1 alone' => [
            new FizzBuzzQuery(6, 3, 6, 'a', 'b'),
            '1,2,b,4,5,ab',
        ];
        yield 'equal integers always yield both strings' => [
            new FizzBuzzQuery(2, 2, 4, 'a', 'b'),
            '1,ab,3,ab',
        ];
        yield 'limit of one' => [
            new FizzBuzzQuery(3, 5, 1, 'fizz', 'buzz'),
            '1',
        ];
        yield 'int1 equal to limit replaces the last value' => [
            new FizzBuzzQuery(7, 9, 7, 'fizz', 'buzz'),
            '1,2,3,4,5,6,fizz',
        ];
    }

    #[DataProvider('cases')]
    public function testGenerate(FizzBuzzQuery $query, string $expected): void
    {
        $values = iterator_to_array((new FizzBuzzGenerator())->generate($query), false);

        self::assertSame($expected, implode(',', $values));
    }
}
