<?php

declare(strict_types=1);

namespace TestFixtures;

/**
 * A class that extends and implements.
 */
class HierarchyClass extends AbstractFixture implements InterfaceFixture
{
    use TraitFixture;

    public function handle(): void
    {
    }

    public function doSomething(string $input): bool
    {
        return true;
    }

    public function process(int $id, string $data): array
    {
        return [];
    }
}
