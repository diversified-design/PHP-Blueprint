<?php

declare(strict_types=1);

namespace TestFixtures;

/**
 * An abstract base class.
 */
abstract class AbstractFixture
{
    abstract public function handle(): void;

    public function baseMethod(): string
    {
        return 'base';
    }
}
