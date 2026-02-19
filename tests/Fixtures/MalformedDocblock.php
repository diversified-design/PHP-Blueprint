<?php

declare(strict_types=1);

namespace TestFixtures;

/**
 * Class with intentionally malformed docblocks.
 */
class MalformedDocblock
{
    /**
     * @param this is not valid
     * @return also broken
     */
    public function brokenTags(string $input): string
    {
        return $input;
    }

    /**
     * @param ??? $notReal
     * @throws
     */
    public function weirdTags(int $count): void
    {
    }

    /**
     * Normal summary line.
     */
    public function normalMethod(): bool
    {
        return true;
    }

    public function noDocblock(string $arg): string
    {
        return $arg;
    }
}
