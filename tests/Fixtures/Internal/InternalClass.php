<?php

declare(strict_types=1);

namespace TestFixtures\Internal;

/**
 * An internal class that should be skipped when skipInternal is true.
 */
class InternalClass
{
    public function internalMethod(): void
    {
    }
}
