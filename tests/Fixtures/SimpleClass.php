<?php

declare(strict_types=1);

namespace TestFixtures;

/**
 * A simple class for testing basic extraction.
 *
 * @internal This second paragraph should be ignored because it follows a blank line.
 */
class SimpleClass
{
    public string $name;

    public int $count = 0;

    protected string $secret = 'hidden';

    private bool $internal = false;

    public function __construct(string $name, int $count = 0)
    {
        $this->name  = $name;
        $this->count = $count;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the name to a new value.
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    protected function protectedMethod(): void
    {
    }

    private function privateMethod(): void
    {
    }

    /** Magic method — should be skipped */
    public function __toString(): string
    {
        return $this->name;
    }
}
