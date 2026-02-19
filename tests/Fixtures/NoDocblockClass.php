<?php

declare(strict_types=1);

namespace TestFixtures;

class NoDocblockClass
{
    public string $name;

    public static int $counter = 0;

    public readonly string $id;

    public function __construct(string $name, string $id = 'default')
    {
        $this->name = $name;
        $this->id = $id;
    }

    public function greet(): string
    {
        return 'Hello, ' . $this->name;
    }

    public function withDefaults(
        string $a = 'hello',
        int $b = 42,
        float $c = 3.14,
        bool $d = true,
        ?string $e = null,
        array $f = [],
    ): void {
    }
}
