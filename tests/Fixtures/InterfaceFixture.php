<?php

declare(strict_types=1);

namespace TestFixtures;

/**
 * A test interface.
 */
interface InterfaceFixture
{
    public function doSomething(string $input): bool;

    public function process(int $id, string $data): array;
}
