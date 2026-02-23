# PHP Blueprint

A reflection-based tool that generates a JSON blueprint of a PHP library's class signatures, optimised for use as LLM context.

## Purpose

When providing library context to coding agents (like Claude Code), you want the class signatures (methods, types, docblocks) without the implementation noise. This tool gives you the equivalent of TypeScript `.d.ts` files or C/C++ headers — the public contract without implementation details.

## Installation

```bash
composer require --dev diversified-design/php-blueprint
```

## Usage

```bash
# Basic — extract all classes from a directory
vendor/bin/blueprint src/

# Filter by namespace (recommended)
vendor/bin/blueprint src/ --namespace="Vendor\\Package"

# Specify output file
vendor/bin/blueprint src/ --namespace="Vendor\\Package" -o blueprint.json

# Include private/protected members
vendor/bin/blueprint src/ --namespace="Vendor\\Package" --include-private

# Truncate docs to first sentence
vendor/bin/blueprint src/ --namespace="Vendor\\Package" --short-docs

# All options
vendor/bin/blueprint src/ \
  --namespace="Vendor\\Package" \
  -o blueprint.json \
  --include-private \
  --include-internal \
  --short-docs \
  --compact-enums
```

### Options

| Option | Short | Description |
|--------|-------|-------------|
| `--output` | `-o` | Output JSON file path (default: `blueprint.json` in working directory) |
| `--namespace` | | Filter by namespace prefix (e.g. `Vendor\\Package`) |
| `--exclude` | | Exclude namespace prefix (repeatable) |
| `--include-private` | | Include private/protected members |
| `--include-internal` | | Include `\Internal\` namespace classes |
| `--short-docs` | | Truncate doc summaries to first sentence |
| `--compact-enums` | | Truncate large constant/enum lists (>5 entries) |
| `--config` | `-c` | Path to config file |
| `--no-config` | | Ignore config file |

## Configuration

Create a `.blueprint.config.php` in your project root:

```php
<?php

return [
    'path'             => 'src/',
    'output'           => 'blueprint.json',
    'namespace'        => 'Vendor\\Package',
    'exclude'          => [
        'Vendor\\Package\\Internal\\',
        'Vendor\\Package\\Debug\\',
    ],
    'include-private'  => false,
    'include-internal' => false,
    'short-docs'       => true,
    'compact-enums'    => false,
];
```

All keys are optional. When a config file is present, you can run Blueprint with no arguments:

```bash
vendor/bin/blueprint
```

CLI arguments always override config values. Use `--no-config` to ignore the config file, or `--config path/to/config.php` to use a different one.

### Composer Scripts

Add to your project's `composer.json`:

```json
{
    "scripts": {
        "blueprint": "blueprint src/ --namespace=Vendor\\\\Package -o blueprint.json"
    }
}
```

Then run with `composer blueprint`.

### CI/CD

```yaml
# GitHub Actions example
- name: Generate PHP library blueprint
  run: vendor/bin/blueprint src/ --namespace="Vendor\\Package" -o blueprint.json

- name: Upload as artifact
  uses: actions/upload-artifact@v4
  with:
    name: php-blueprint
    path: blueprint.json
```

## Output Format

The extractor generates pretty-printed JSON:

```json
{
    "Namespace\\ClassName": {
        "doc": "Class summary from docblock.",
        "extends": "ParentClass",
        "implements": ["Interface1"],
        "uses": ["Trait1"],
        "constants": {
            "CONSTANT_NAME": "value"
        },
        "properties": [
            "static readonly string $name"
        ],
        "methods": [
            "__construct(string $arg, int $count = 0)",
            "doSomething(string $input): bool — Brief description"
        ]
    }
}
```

Only non-empty fields are included. The `type` field is omitted for plain classes (only shown for interfaces, traits, and enums).

## Output Modes

### Public-Only (Default)

Extracts only `public` methods and properties — the true public API surface.

**Use for:** LLM context (most token-efficient), API documentation, public interface contracts.

### Full Mode (`--include-private`)

Includes `private` and `protected` members.

**Use for:** Internal refactoring analysis, understanding implementation details, migration planning.

## Writing Code That Blueprints Well

Blueprint extracts what's in your source — it can't invent context that isn't there. The more information your code carries in its signatures and docblocks, the more useful the blueprint is to an LLM agent. Here's what matters most:

### Type everything

Blueprint captures parameter types, return types, and property types. When a PHPStan/Psalm `@param`, `@return`, or `@var` docblock type is present, Blueprint uses it instead of the native type hint — giving agents the full picture.

```php
// Weak — agent has to guess
function fetch($url, $options) { ... }

// Better — agent knows the basic types
function fetch(string $url, array $options = [], int $timeout = 30): Response { ... }

// Best — agent knows the exact shape
/** @param array{timeout: int, retries: int, base_uri: string} $options */
function fetch(string $url, array $options = [], int $timeout = 30): Response { ... }
```

### Write summary docblocks

Blueprint extracts the **first paragraph** of each class and method docblock (everything before the first `@tag` or blank line). This becomes the `— description` in the output. One or two sentences that explain *what* the method does and *when* you'd use it:

```php
/**
 * Atomically write content to a file using a temp-file-and-rename strategy.
 *
 * Detailed implementation notes go here — Blueprint ignores
 * everything after the first blank line or @tag.
 */
public function dumpFile(string $filename, string $content): void
```

### Document parameters with `@param`

Blueprint extracts `@param` descriptions and appends them as inline comments next to each parameter. This is especially valuable for parameters whose purpose isn't obvious from the type alone:

```php
/**
 * @param int $mode The new file mode (octal, e.g. 0755)
 * @param bool $recursive Whether to apply recursively to subdirectories
 */
public function chmod(string $path, int $mode, bool $recursive = false): void
```

In the blueprint, this becomes:
```
"chmod(string $path, int $mode /*The new file mode (octal, e.g. 0755)*/, bool $recursive = false /*Whether to apply recursively to subdirectories*/): void"
```

### Declare `@throws`

Blueprint appends `@throws` types to method signatures. This tells agents which exceptions to handle:

```php
/**
 * @throws FileNotFoundException
 * @throws IOException
 */
public function copy(string $origin, string $target): void
```

### Use meaningful names

Parameter names appear directly in the blueprint. `$baseDirectory` communicates more than `$dir`. `$overwriteNewerFiles` communicates more than `$force`. Since the blueprint may be the *only* context an agent has, every name carries weight.

### Use constants and enums

Blueprint extracts constant names and values, and enum cases with their backing values. Well-named constants tell an agent what valid states and options exist:

```php
const STATUS_PENDING = 'pending';
const STATUS_ACTIVE  = 'active';

enum Suit: string {
    case Hearts   = 'H';
    case Diamonds = 'D';
}
```

### Define clear interfaces

Blueprint captures the full class hierarchy — `extends`, `implements`, and `uses`. Well-defined interfaces and abstract classes give agents the contract to code against without needing to read implementations.

## How It Works

Blueprint uses **static analysis** via [BetterReflection](https://github.com/Roave/BetterReflection) (which builds on `nikic/php-parser`) to extract class signatures. No code is executed — source files are parsed, not loaded. This means:

- No side effects from target code
- No dependency resolution failures
- No PHP version coupling between Blueprint and the target package
- Works on any valid PHP source, even if dependencies aren't installed

## Token Efficiency

For LLM context, token efficiency matters:

```
Full source code:     ~50,000 lines, ~2MB
Public API JSON:       1,935 lines,  72KB  (97% reduction)
Full API JSON:         2,528 lines,  92KB  (95% reduction)
```

## Requirements

- PHP 8.2+
- Composer

## Limitations

- Doesn't extract standalone function signatures (only classes/interfaces/traits/enums)
- Docblocks are cleaned but not fully parsed (no structured `@param` extraction beyond inline hints)
- Complex constant expressions (runtime-evaluated) are shown as `<expr>` rather than resolved values

## License

MIT
