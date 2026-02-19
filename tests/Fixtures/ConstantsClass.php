<?php

declare(strict_types=1);

namespace TestFixtures;

class ConstantsClass
{
    public const VERSION = '1.0.0';

    public const MAX_RETRIES = 3;

    public const ENABLED = true;

    public const TAGS = ['alpha', 'beta', 'gamma'];

    protected const SECRET_KEY = 'hidden';

    private const INTERNAL_FLAG = false;

    public function getVersion(): string
    {
        return self::VERSION;
    }
}
