## Context

A Composer package (`diversified-design/php-blueprint`) that generates a JSON blueprint of a PHP library's class signatures, optimised for LLM context. The equivalent of TypeScript `.d.ts` files — the public contract without implementation noise.

Always read `./blueprint.json` for the library's own latest signature blueprint. 

## Project Structure

```
bin/blueprint                    — CLI entry point (symfony/console)
src/BlueprintGenerator.php       — Core extraction logic (Blueprint\BlueprintGenerator)
src/Command/ExtractCommand.php   — CLI command (Blueprint\Command\ExtractCommand)
.blueprint.config.php             — Optional project config (PHP array, auto-discovered in cwd)
examples/                        — Sample output files
```

## How It Works

1. Uses BetterReflection (static analysis via nikic/php-parser) — no code execution
2. `DirectoriesSourceLocator` scans the target directory for PHP files
3. `AutoloadSourceLocator` + `PhpInternalSourceLocator` resolve parent classes and dependencies
4. Extracts: metadata, hierarchy, constants, properties, methods, docblocks
5. Outputs compact JSON — methods/properties as signature strings, not nested objects

## Usage

```bash
vendor/bin/blueprint src/ --namespace="Vendor\\Package" -o blueprint.json
```

## Key Design Decisions

- **Static analysis, no execution** — source files are parsed, not loaded; no side effects, no dependency failures
- **BetterReflection** — provides the Reflection API over parsed source code (roave/better-reflection)
- **Compact signatures** — methods stored as strings like `doThing(string $arg): bool — Summary` rather than nested objects, for token efficiency
- **Omit-when-empty** — no `"type": "class"`, no empty arrays; only non-default/non-empty fields appear
