<?php

declare(strict_types=1);

/**
 * Blueprint configuration file.
 *
 * All keys are optional — missing keys use CLI defaults.
 * CLI arguments always override values set here.
 *
 * @see https://github.com/BorisAnthony/PHP-Blueprint
 */
return [
    // Directory to scan for PHP files
    'path' => 'src/',

    // Output file path (relative to working directory)
    'output' => 'blueprint.json',

    // Include only classes under this namespace prefix
    'namespace' => 'Vendor\\Package',

    // Exclude specific namespace prefixes (applied after namespace filter)
    'exclude' => [
        // 'Vendor\\Package\\Internal\\',
        // 'Vendor\\Package\\Debug\\',
    ],

    // Include private/protected members (default: false — public only)
    'include-private' => false,

    // Include \Internal\ namespace classes (default: false — skip them)
    'include-internal' => false,

    // Truncate doc summaries to first sentence
    'short-docs' => false,

    // Truncate large constant/enum lists (>5 entries)
    'compact-enums' => false,
];
