<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class LimitCapValidator extends ConstraintValidator
{
    public function __construct(
        #[Autowire(env: 'int:FIZZBUZZ_MAX_LIMIT')]
        private int $maxLimit,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof LimitCap) {
            throw new UnexpectedTypeException($constraint, LimitCap::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_int($value)) {
            throw new UnexpectedValueException($value, 'int');
        }

        if ($value > $this->maxLimit) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ max }}', (string) $this->maxLimit)
                ->addViolation();
        }
    }
}
