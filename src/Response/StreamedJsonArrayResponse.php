<?php

declare(strict_types=1);

namespace App\Response;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class StreamedJsonArrayResponse extends StreamedResponse
{
    private const int CHUNK_SIZE = 8192;
    private const int JSON_FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

    /**
     * @param iterable<string> $items
     */
    public function __construct(iterable $items)
    {
        parent::__construct(fn () => $this->stream($items), headers: ['Content-Type' => 'application/json']);
    }

    /**
     * @param iterable<string> $items
     */
    private function stream(iterable $items): void
    {
        echo '[';
        $chunk = [];
        $separator = '';
        foreach ($items as $item) {
            $chunk[] = $item;
            if (self::CHUNK_SIZE === \count($chunk)) {
                $this->write($separator, $chunk);
                $chunk = [];
                $separator = ',';
            }
        }
        if ([] !== $chunk) {
            $this->write($separator, $chunk);
        }
        echo ']';
        flush();
    }

    /**
     * @param list<string> $chunk
     */
    private function write(string $separator, array $chunk): void
    {
        echo $separator, substr(json_encode($chunk, self::JSON_FLAGS), 1, -1);
        flush();
    }
}
