<?php

declare(strict_types=1);

namespace Blueprint;

use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\EncodeOptions;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionEnum;
use Roave\BetterReflection\Reflection\ReflectionIntersectionType;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionNamedType;
use Roave\BetterReflection\Reflection\ReflectionType;
use Roave\BetterReflection\Reflection\ReflectionUnionType;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * Generates a JSON blueprint of a PHP library's class signatures
 * via static analysis (no code execution), suitable for LLM context.
 */
class BlueprintGenerator
{
    /** @var array<string, array<string, mixed>> */
    private array $apiMap = [];

    private string $namespaceFilter;

    private bool $publicOnly;

    private bool $skipInternal;

    private bool $shortDocs;

    private int $compactEnumsThreshold;

    /** @var list<string> */
    private array $excludeNamespaces;

    private Lexer $lexer;

    private PhpDocParser $phpDocParser;

    /**
     * @param list<string> $excludeNamespaces
     */
    public function __construct(string $namespaceFilter = '', bool $publicOnly = true, bool $skipInternal = true, bool $shortDocs = false, int $compactEnumsThreshold = 0, array $excludeNamespaces = [])
    {
        $this->namespaceFilter       = $namespaceFilter;
        $this->publicOnly            = $publicOnly;
        $this->skipInternal          = $skipInternal;
        $this->shortDocs             = $shortDocs;
        $this->compactEnumsThreshold = $compactEnumsThreshold;
        $this->excludeNamespaces     = $excludeNamespaces;

        $config              = new ParserConfig([]);
        $this->lexer         = new Lexer($config);
        $constExprParser     = new ConstExprParser($config);
        $typeParser          = new TypeParser($config, $constExprParser);
        $this->phpDocParser  = new PhpDocParser($config, $typeParser, $constExprParser);
    }

    /**
     * Extracts class signatures from a directory of PHP files.
     *
     * @return array<string, array<string, mixed>>
     */
    public function extractFromDirectory(string $directory): array
    {
        $this->apiMap = [];

        $betterReflection = new BetterReflection();
        $astLocator       = $betterReflection->astLocator();

        // DirectoriesSourceLocator scans the target directory (provides class enumeration).
        // AutoloadSourceLocator + PhpInternalSourceLocator resolve parent classes,
        // interfaces, and traits from dependencies without enumerating them.
        $sourceLocator = new AggregateSourceLocator([
            new DirectoriesSourceLocator([$directory], $astLocator),
            new AutoloadSourceLocator($astLocator),
            new PhpInternalSourceLocator($astLocator, $betterReflection->sourceStubber()),
        ]);
        $reflector = new DefaultReflector($sourceLocator);

        foreach ($reflector->reflectAllClasses() as $reflection) {
            if ($this->shouldProcessClass($reflection)) {
                $this->extractClassInfo($reflection);
            }
        }

        ksort($this->apiMap);

        return $this->apiMap;
    }

    private function shouldProcessClass(ReflectionClass $reflection): bool
    {
        if ($this->namespaceFilter === '') {
            return true;
        }

        $className = $reflection->getName();

        if (!str_starts_with($className, $this->namespaceFilter)) {
            return false;
        }

        // Skip \Internal\ sub-namespace when configured
        if ($this->skipInternal) {
            $relative = substr($className, strlen($this->namespaceFilter));
            if (str_contains($relative, '\\Internal\\') || str_starts_with($relative, 'Internal\\')) {
                return false;
            }
        }

        // Skip explicitly excluded namespace prefixes
        foreach ($this->excludeNamespaces as $excluded) {
            if (str_starts_with($className, $excluded)) {
                return false;
            }
        }

        return true;
    }

    private function extractClassInfo(ReflectionClass $reflection): void
    {
        $info = [];

        // Type (only if not a plain class)
        $type = $this->getClassType($reflection);
        if ($type !== 'class') {
            $info['type'] = $type;
        }

        // Flags — only when true
        if ($reflection->isAbstract() && $type !== 'interface') {
            $info['abstract'] = true;
        }
        if ($reflection->isFinal()) {
            $info['final'] = true;
        }

        // Docblock — summary line only
        $doc = $this->extractSummary($reflection->getDocComment());
        if ($doc !== null) {
            $info['doc'] = $doc;
        }

        // Hierarchy
        $parent = $reflection->getParentClass();
        if ($parent) {
            $info['extends'] = $parent->getName();
        }

        $interfaces = $this->getOwnInterfaces($reflection);
        if (!empty($interfaces)) {
            $info['implements'] = $interfaces;
        }

        $traits = $reflection->getTraitNames();
        if (!empty($traits)) {
            $info['uses'] = $traits;
        }

        // Constants
        $constants = $this->extractConstants($reflection);
        if (!empty($constants)) {
            $info['constants'] = $constants;
        }

        // Properties
        $properties = $this->extractProperties($reflection);
        if (!empty($properties)) {
            $info['properties'] = $properties;
        }

        // Methods
        $methods = $this->extractMethods($reflection);
        if (!empty($methods)) {
            $info['methods'] = $methods;
        }

        $this->apiMap[$reflection->getName()] = $info;
    }

    private function getClassType(ReflectionClass $reflection): string
    {
        if ($reflection->isInterface()) {
            return 'interface';
        }
        if ($reflection->isTrait()) {
            return 'trait';
        }
        if ($reflection->isEnum()) {
            return 'enum';
        }

        return 'class';
    }

    /**
     * Get only interfaces directly implemented by this class (not inherited).
     *
     * @return list<string>
     */
    private function getOwnInterfaces(ReflectionClass $reflection): array
    {
        $immediate = $reflection->getImmediateInterfaces();

        // Filter out UnitEnum/BackedEnum (auto-added for enums)
        $exclude = ['UnitEnum', 'BackedEnum'];
        $result  = [];
        foreach ($immediate as $iface) {
            if (!in_array($iface->getName(), $exclude, true)) {
                $result[] = $iface->getName();
            }
        }

        return $result;
    }

    /** @return array<string|int, mixed> */
    private function extractConstants(ReflectionClass $reflection): array
    {
        $type   = $this->getClassType($reflection);
        $result = [];

        // For enums, list the case names/values via ReflectionEnum
        if ($type === 'enum' && $reflection instanceof ReflectionEnum) {
            $isBacked = $reflection->isBacked();
            foreach ($reflection->getCases() as $case) {
                if ($isBacked) {
                    try {
                        $result[$case->getName()] = $case->getValue();
                    } catch (Throwable) {
                        $result[$case->getName()] = null;
                    }
                } else {
                    $result[] = $case->getName();
                }
            }

            // Also include non-case constants defined on the enum
            foreach ($reflection->getImmediateConstants() as $const) {
                if ($this->publicOnly && !$const->isPublic()) {
                    continue;
                }
                $result[$const->getName()] = $this->safeGetValue($const);
            }

            return $this->truncateConstants($result);
        }

        foreach ($reflection->getImmediateConstants() as $const) {
            if ($this->publicOnly && !$const->isPublic()) {
                continue;
            }
            $result[$const->getName()] = $this->compactConstantValue($this->safeGetValue($const));
        }

        return !empty($result) ? $this->truncateConstants($result) : [];
    }

    /**
     * Safely get a constant's value, falling back gracefully for unresolvable expressions.
     */
    private function safeGetValue(mixed $const): mixed
    {
        try {
            return $const->getValue();
        } catch (Throwable) {
            return '<expr>';
        }
    }

    /**
     * Truncate a constants array if it exceeds the compact-enums threshold.
     *
     * @param  array<string|int, mixed> $constants
     * @return array<string|int, mixed>
     */
    private function truncateConstants(array $constants): array
    {
        $threshold = $this->compactEnumsThreshold;
        if ($threshold <= 0 || count($constants) <= $threshold) {
            return $constants;
        }

        $total            = count($constants);
        $truncated        = array_slice($constants, 0, $threshold, true);
        $truncated['...'] = '('.$total.' total)';

        return $truncated;
    }

    /**
     * Summarise deeply nested or large constant values.
     */
    private function compactConstantValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        // Flat arrays with only scalar values: keep as-is
        $hasNested = false;
        foreach ($value as $v) {
            if (is_array($v)) {
                $hasNested = true;

                break;
            }
        }
        if (!$hasNested) {
            return $this->compactEnumsThreshold > 0 && count($value) > $this->compactEnumsThreshold
                ? array_merge(array_slice($value, 0, $this->compactEnumsThreshold, true), ['...' => '('.count($value).' total)'])
                : $value;
        }
        // Nested structure: summarise as "array<key-type, shape>"
        $count    = count($value);
        $firstKey = array_key_first($value);
        $firstVal = $value[$firstKey];
        $keys     = is_string($firstKey) ? 'string' : 'int';
        if (is_array($firstVal)) {
            $shape = implode(', ', array_keys($firstVal));

            return "array<{$keys}, {{$shape}}> ({$count} entries)";
        }

        return "array<{$keys}> ({$count} entries)";
    }

    /**
     * Extract properties as compact signature strings.
     * Format: "Type $name" or "static Type $name = default"
     *
     * @return list<string>
     */
    private function extractProperties(ReflectionClass $reflection): array
    {
        $result = [];
        foreach ($reflection->getImmediateProperties() as $property) {
            if ($this->publicOnly && !$property->isPublic()) {
                continue;
            }
            // Skip the auto-generated 'name' and 'value' properties on enums
            if ($this->getClassType($reflection) === 'enum' && in_array($property->getName(), ['name', 'value'], true)) {
                continue;
            }

            // Parse @var type from property docblock
            $varType = $this->extractVarType($property->getDocComment());

            $sig = '';
            if ($property->isStatic()) {
                $sig .= 'static ';
            }
            if ($property->isReadOnly()) {
                $sig .= 'readonly ';
            }

            // Prefer docblock @var type over native type
            if ($varType !== null) {
                $sig .= $varType.' ';
            } elseif ($property->hasType()) {
                $sig .= $this->getTypeString($property->getType()).' ';
            }
            $sig .= '$'.$property->getName();

            $result[] = $sig;
        }

        return $result;
    }

    /**
     * Extract methods as compact signature strings.
     * Format: "static methodName(Type $param, Type $param2 = default): ReturnType — Summary"
     *
     * @return list<string>
     */
    private function extractMethods(ReflectionClass $reflection): array
    {
        $result = [];
        foreach ($reflection->getImmediateMethods() as $method) {
            if ($this->publicOnly && !$method->isPublic()) {
                continue;
            }
            // Skip magic methods that aren't constructors
            if (str_starts_with($method->getName(), '__') && $method->getName() !== '__construct') {
                continue;
            }
            // Skip auto-generated enum methods
            if ($this->getClassType($reflection) === 'enum') {
                $enumMethods = ['cases', 'from', 'tryFrom'];
                if (in_array($method->getName(), $enumMethods, true)) {
                    continue;
                }
            }

            $docComment = $method->getDocComment();
            $docTags    = $this->parseDocBlock($docComment);

            $sig = '';
            if ($method->isStatic()) {
                $sig .= 'static ';
            }
            $sig .= $method->getName().'(';
            $sig .= $this->formatParameters($method, $docTags['params']);
            $sig .= ')';

            // Return type: prefer docblock @return over native type
            $returnType = $docTags['return'];
            if ($returnType === null && $method->hasReturnType()) {
                $returnType = $this->getTypeString($method->getReturnType());
            }
            if ($returnType !== null) {
                $sig .= ': '.$returnType;
            }

            // Append brief doc summary if available
            $doc = $this->extractSummary($docComment);
            if ($doc !== null) {
                $sig .= ' — '.$doc;
            }

            // Append @throws if present
            if (!empty($docTags['throws'])) {
                $sig .= ' @throws '.implode('|', $docTags['throws']);
            }

            $result[] = $sig;
        }

        return $result;
    }

    /**
     * @param array<string, array{type: string|null, description: string}> $paramDocs
     */
    private function formatParameters(ReflectionMethod $method, array $paramDocs = []): string
    {
        $params = [];
        foreach ($method->getParameters() as $param) {
            $p        = '';
            $paramKey = '$'.$param->getName();
            $docType  = $paramDocs[$paramKey]['type'] ?? null;

            // Prefer docblock type over native type
            if ($docType !== null) {
                $p .= $docType.' ';
            } elseif ($param->hasType()) {
                $p .= $this->getTypeString($param->getType()).' ';
            }

            if ($param->isVariadic()) {
                $p .= '...';
            }
            $p .= '$'.$param->getName();

            if ($param->isOptional() && !$param->isVariadic()) {
                if ($param->isDefaultValueAvailable()) {
                    try {
                        $default = $param->getDefaultValue();
                        $p .= ' = '.$this->formatDefaultValue($default);
                    } catch (Throwable) {
                        // BetterReflection can't resolve complex expressions
                        $p .= ' = ...';
                    }
                } else {
                    $p .= ' = ?';
                }
            }

            // Append @param description if available
            $description = $paramDocs[$paramKey]['description'] ?? '';
            if ($description !== '') {
                $p .= ' /*'.$description.'*/';
            }

            $params[] = $p;
        }

        return implode(', ', $params);
    }

    private function formatDefaultValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_string($value)) {
            return "'".addslashes($value)."'";
        }
        if (is_array($value)) {
            return '[]';
        }

        return (string) $value;
    }

    private function getTypeString(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            return $type->allowsNull() && $name !== 'mixed' ? '?'.$name : $name;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(fn ($t) => (string) $t, $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(fn ($t) => (string) $t, $type->getTypes()));
        }

        return (string) $type;
    }

    /**
     * Parse a docblock using phpstan/phpdoc-parser to extract typed @param, @return, and @throws tags.
     *
     * @return array{throws: list<string>, params: array<string, array{type: string|null, description: string}>, return: string|null}
     */
    private function parseDocBlock(?string $docblock): array
    {
        $result = ['throws' => [], 'params' => [], 'return' => null];
        if (!$docblock) {
            return $result;
        }

        try {
            $tokens     = new TokenIterator($this->lexer->tokenize($docblock));
            $phpDocNode = $this->phpDocParser->parse($tokens);

            // @param tags
            foreach ($phpDocNode->getParamTagValues() as $tag) {
                $result['params'][$tag->parameterName] = [
                    'type'        => (string) $tag->type,
                    'description' => trim($tag->description),
                ];
            }

            // @return tag (first one wins)
            foreach ($phpDocNode->getReturnTagValues() as $tag) {
                $result['return'] = (string) $tag->type;

                break;
            }

            // @throws tags
            foreach ($phpDocNode->getThrowsTagValues() as $tag) {
                $result['throws'][] = (string) $tag->type;
            }
            $result['throws'] = array_values(array_unique($result['throws']));
        } catch (Throwable) {
            // Malformed docblock — return empty results
        }

        return $result;
    }

    /**
     * Extract @var type from a property docblock.
     */
    private function extractVarType(?string $docblock): ?string
    {
        if (!$docblock) {
            return null;
        }

        try {
            $tokens     = new TokenIterator($this->lexer->tokenize($docblock));
            $phpDocNode = $this->phpDocParser->parse($tokens);

            foreach ($phpDocNode->getVarTagValues() as $tag) {
                return (string) $tag->type;
            }
        } catch (Throwable) {
            // Malformed docblock
        }

        return null;
    }

    /**
     * Extract only the first summary sentence from a docblock.
     * Strips @tags and returns null if empty.
     */
    private function extractSummary(?string $docblock): ?string
    {
        if (!$docblock) {
            return null;
        }

        // Remove comment markers
        $cleaned = preg_replace('/^\s*\/?\*+\/?\s*$/m', '', $docblock);
        $cleaned = preg_replace('/^\s*\*\s?/m', '', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return null;
        }

        // Take everything before the first @tag or blank line
        $lines   = explode("\n", $cleaned);
        $summary = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '@')) {
                break;
            }
            $summary[] = $trimmed;
        }

        $text = implode(' ', $summary);
        if ($text === '') {
            return null;
        }

        // In short mode, truncate to the first sentence
        if ($this->shortDocs) {
            if (preg_match('/^(.+?[.!?])(?:\s|$)/', $text, $m)) {
                $text = $m[1];
            }
        }

        return $text;
    }

    public function toJson(): string
    {
        return json_encode($this->apiMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function toToon(): string
    {
        $options = new EncodeOptions(
            indent: 4,         // 2 == default
            // delimiter: "\t"    // , == default
        );

        return Toon::encode($this->apiMap, $options);
    }

    /**
     * @param 'json'|'toon'|'both' $format
     */
    public function saveToFile(string $filepath, string $format = 'json'): void
    {
        $fs = new Filesystem();

        if ($format === 'both') {
            $jsonPath = preg_replace('/\.toon$/', '.json', $filepath);
            $toonPath = preg_replace('/\.json$/', '.toon', $filepath);
            $fs->dumpFile($jsonPath, $this->toJson());
            $fs->dumpFile($toonPath, $this->toToon());

            return;
        }

        $fs->dumpFile($filepath, $format === 'toon' ? $this->toToon() : $this->toJson());
    }
}
