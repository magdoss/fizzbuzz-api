<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\FizzBuzzQuery;

final class FizzBuzzGenerator
{
    /**
     * @return \Generator<int, string>
     */
    public function generate(FizzBuzzQuery $query): \Generator
    {
        for ($i = 1; $i <= $query->limit; ++$i) {
            $word = '';
            if (0 === $i % $query->int1) {
                $word .= $query->str1;
            }
            if (0 === $i % $query->int2) {
                $word .= $query->str2;
            }

            yield '' === $word ? (string) $i : $word;
        }
    }
}
