<?php

declare(strict_types=1);

namespace TestFixtures;

/**
 * Class with rich PHPStan docblock types for testing type extraction.
 */
class DocblockRichClass
{
    /** @var array<string, mixed> */
    public array $config = [];

    /** @var list<string> */
    public array $tags = [];

    /**
     * Process items with detailed type info.
     *
     * @param array{name: string, age: int, active: bool} $item The structured item to process
     * @param list<string> $filters Optional filter list
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function processItem(array $item, array $filters = []): array
    {
        return $item;
    }

    /**
     * Fetch data from a source.
     *
     * @param string|resource $source The data source
     * @param array<int, callable(string): bool> $validators
     * @return list<array{id: int, value: string}>
     */
    public function fetchData(mixed $source, array $validators = []): array
    {
        return [];
    }

    /**
     * Method with only a description, no type overrides.
     */
    public function simpleMethod(string $name): string
    {
        return $name;
    }

    /**
     * @param int $mode The file mode (octal, e.g. 0755)
     * @param bool $recursive Whether to apply recursively
     */
    public function chmod(string $path, int $mode, bool $recursive = false): void
    {
    }
}
