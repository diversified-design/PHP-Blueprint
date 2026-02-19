<?php

declare(strict_types=1);

namespace TestFixtures;

/**
 * A test trait for logging.
 */
trait TraitFixture
{
    public function log(string $message): void
    {
    }

    protected function debug(string $context, mixed $data): void
    {
    }
}
