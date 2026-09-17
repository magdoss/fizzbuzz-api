<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class LimitCap extends Constraint
{
    public string $message = 'This value should be less than or equal to {{ max }}.';
}
