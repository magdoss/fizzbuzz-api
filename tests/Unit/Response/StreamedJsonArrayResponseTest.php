<?php

declare(strict_types=1);

namespace App\Tests\Unit\Response;

use App\Response\StreamedJsonArrayResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StreamedJsonArrayResponseTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function sizes(): iterable
    {
        yield 'empty' => [0];
        yield 'one item' => [1];
        yield 'exactly one chunk' => [8192];
        yield 'one chunk and one item' => [8193];
        yield 'two chunks' => [16384];
    }

    #[DataProvider('sizes')]
    public function testOutputIsAValidJsonListOfAllItems(int $size): void
    {
        $items = array_map(strval(...), range(1, $size));
        $response = new StreamedJsonArrayResponse(new \ArrayIterator($items));

        ob_start();
        $response->sendContent();
        $output = (string) ob_get_clean();

        self::assertSame($items, json_decode($output, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testContentTypeIsJson(): void
    {
        self::assertSame('application/json', (new StreamedJsonArrayResponse([]))->headers->get('Content-Type'));
    }
}
