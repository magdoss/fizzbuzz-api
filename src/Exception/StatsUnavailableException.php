<?php

declare(strict_types=1);

namespace App\Exception;

final class StatsUnavailableException extends \RuntimeException
{
    public static function because(\Throwable $previous): self
    {
        return new self('Request statistics are unavailable.', 0, $previous);
    }
}
