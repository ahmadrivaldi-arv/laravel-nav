<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Support;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;

final class CallbackEvaluator
{
    /** @param list<mixed> $arguments */
    public function evaluate(mixed $value, array $arguments): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        $reflection = new ReflectionFunction($value);

        if ($reflection->getNumberOfRequiredParameters() > count($arguments)) {
            throw new InvalidArgumentException('Navigation callback requires more arguments than its supported signature.');
        }

        return $value(...($reflection->isVariadic()
            ? $arguments
            : array_slice($arguments, 0, $reflection->getNumberOfParameters())));
    }
}
