<?php

declare(strict_types=1);

namespace TestFixtures;

class VariadicMethod
{
    public function withVariadic(string $first, string ...$rest): string
    {
        return $first . implode('', $rest);
    }

    /**
     * @param list<int> $numbers
     */
    public function withDocVariadic(int ...$numbers): int
    {
        return array_sum($numbers);
    }
}
