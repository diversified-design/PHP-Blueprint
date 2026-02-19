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
vendor/bin/blueprint src/ --namespace="Vendor\\Package" -o api.json

# Include private/protected members
vendor/bin/blueprint src/ --namespace="Vendor\\Package" --include-private

# Truncate docs to first sentence
vendor/bin/blueprint src/ --namespace="Vendor\\Package" --short-docs

# All options
vendor/bin/blueprint src/ \
  --namespace="Vendor\\Package" \
  -o api.json \
  --include-private \
  --include-internal \
  --short-docs \
  --compact-enums
```

### Options

| Option | Short | Description |
|--------|-------|-------------|
| `--output` | `-o` | Output JSON file path (default: `library-api.json`) |
| `--namespace` | | Filter by namespace prefix (e.g. `Vendor\\Package`) |
| `--include-private` | | Include private/protected members |
| `--include-internal` | | Include `\Internal\` namespace classes |
| `--short-docs` | | Truncate doc summaries to first sentence |
| `--compact-enums` | | Truncate large constant/enum lists (>5 entries) |

### Composer Scripts

Add to your project's `composer.json`:

```json
{
    "scripts": {
        "blueprint": "blueprint src/ --namespace=Vendor\\\\Package -o api.json"
    }
}
```

Then run with `composer blueprint`.

### CI/CD

```yaml
# GitHub Actions example
- name: Generate API blueprint
  run: vendor/bin/blueprint src/ --namespace="Vendor\\Package" -o api-surface.json

- name: Upload as artifact
  uses: actions/upload-artifact@v4
  with:
    name: api-blueprint
    path: api-surface.json
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
