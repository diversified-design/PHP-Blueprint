<?php

declare(strict_types=1);

use Blueprint\BlueprintGenerator;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function extractFixtures(
    string $namespace = 'TestFixtures',
    bool $publicOnly = true,
    bool $skipInternal = true,
    bool $shortDocs = false,
    int $compactEnumsThreshold = 0,
    array $excludeNamespaces = [],
): array {
    $gen = new BlueprintGenerator($namespace, $publicOnly, $skipInternal, $shortDocs, $compactEnumsThreshold, $excludeNamespaces);

    return $gen->extractFromDirectory(fixturesPath());
}

/** Find the first element in an array matching a callback, or null. */
function findFirst(array $items, callable $fn): ?string
{
    foreach ($items as $item) {
        if ($fn($item)) {
            return $item;
        }
    }

    return null;
}

// ===========================================================================
// Basic extraction
// ===========================================================================

test('extracts classes from fixture directory', function () {
    $map = extractFixtures();

    expect($map)->toBeArray()
        ->and($map)->toHaveKey('TestFixtures\\SimpleClass');
});

test('output is sorted by fully-qualified class name', function () {
    $map = extractFixtures();
    $keys = array_keys($map);

    $sorted = $keys;
    sort($sorted);

    expect($keys)->toBe($sorted);
});

test('toJson returns valid JSON', function () {
    $gen = new BlueprintGenerator('TestFixtures');
    $gen->extractFromDirectory(fixturesPath());
    $json = $gen->toJson();

    expect(json_decode($json, true))->toBeArray();
});

test('saveToFile writes JSON to disk', function () {
    $gen = new BlueprintGenerator('TestFixtures');
    $gen->extractFromDirectory(fixturesPath());

    $tmp = tempnam(sys_get_temp_dir(), 'blueprint_test_');
    $gen->saveToFile($tmp);

    expect(file_exists($tmp))->toBeTrue();
    expect(json_decode(file_get_contents($tmp), true))->toBeArray();

    unlink($tmp);
});

// ===========================================================================
// Class types
// ===========================================================================

test('plain class omits the "type" field', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\SimpleClass'];

    expect($class)->not->toHaveKey('type');
});

test('interface has type "interface"', function () {
    $map = extractFixtures();
    $iface = $map['TestFixtures\\InterfaceFixture'];

    expect($iface['type'])->toBe('interface');
});

test('trait has type "trait"', function () {
    $map = extractFixtures();
    $trait = $map['TestFixtures\\TraitFixture'];

    expect($trait['type'])->toBe('trait');
});

test('backed enum has type "enum"', function () {
    $map = extractFixtures();
    $enum = $map['TestFixtures\\EnumFixture'];

    expect($enum['type'])->toBe('enum');
});

test('unit enum has type "enum"', function () {
    $map = extractFixtures();
    $enum = $map['TestFixtures\\UnitEnumFixture'];

    expect($enum['type'])->toBe('enum');
});

test('abstract class has "abstract" flag', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\AbstractFixture'];

    expect($class['abstract'])->toBeTrue();
});

test('final class has "final" flag', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\FinalFixture'];

    expect($class['final'])->toBeTrue();
});

// ===========================================================================
// Hierarchy
// ===========================================================================

test('extracts parent class', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\HierarchyClass'];

    expect($class['extends'])->toBe('TestFixtures\\AbstractFixture');
});

test('extracts implemented interfaces', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\HierarchyClass'];

    expect($class['implements'])->toContain('TestFixtures\\InterfaceFixture');
});

test('extracts used traits', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\HierarchyClass'];

    expect($class['uses'])->toContain('TestFixtures\\TraitFixture');
});

// ===========================================================================
// Docblock extraction
// ===========================================================================

test('extracts class summary from docblock', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\SimpleClass'];

    expect($class['doc'])->toBe('A simple class for testing basic extraction.');
});

test('ignores content after blank line in docblock', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\SimpleClass'];

    expect($class['doc'])->not->toContain('second paragraph');
});

test('class without docblock has no "doc" key', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\NoDocblockClass'];

    expect($class)->not->toHaveKey('doc');
});

test('short-docs truncates to first sentence', function () {
    $map = extractFixtures(shortDocs: true);
    $class = $map['TestFixtures\\DocblockRichClass'];

    expect($class['doc'])->toBe('Class with rich PHPStan docblock types for testing type extraction.');
});

// ===========================================================================
// Method extraction
// ===========================================================================

test('extracts public methods', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $sigs = implode("\n", $methods);

    expect($sigs)->toContain('__construct(')
        ->and($sigs)->toContain('getName(')
        ->and($sigs)->toContain('setName(')
        ->and($sigs)->toContain('static create(');
});

test('skips magic methods except __construct', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $sigs = implode("\n", $methods);

    expect($sigs)->not->toContain('__toString');
});

test('skips protected/private methods in public-only mode', function () {
    $map = extractFixtures(publicOnly: true);
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $sigs = implode("\n", $methods);

    expect($sigs)->not->toContain('protectedMethod')
        ->and($sigs)->not->toContain('privateMethod');
});

test('includes protected/private methods when publicOnly is false', function () {
    $map = extractFixtures(publicOnly: false);
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $sigs = implode("\n", $methods);

    expect($sigs)->toContain('protectedMethod')
        ->and($sigs)->toContain('privateMethod');
});

test('static methods are prefixed with "static"', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $createSig = findFirst($methods, fn ($m) => str_contains($m, 'create'));

    expect($createSig)->toStartWith('static ');
});

test('method summary is appended after dash', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $setSig = findFirst($methods, fn ($m) => str_contains($m, 'setName'));

    expect($setSig)->toContain(' — Set the name to a new value.');
});

test('skips auto-generated enum methods', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\EnumFixture']['methods'];
    $sigs = implode("\n", $methods);

    expect($sigs)->not->toContain('cases(')
        ->and($sigs)->not->toContain('from(')
        ->and($sigs)->not->toContain('tryFrom(');
});

test('enum custom methods are extracted', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\EnumFixture']['methods'];
    $sigs = implode("\n", $methods);

    expect($sigs)->toContain('label()');
});

// ===========================================================================
// Parameter formatting
// ===========================================================================

test('parameters include types and names', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $ctor = findFirst($methods, fn ($m) => str_contains($m, '__construct'));

    expect($ctor)->toContain('string $name')
        ->and($ctor)->toContain('int $count = 0');
});

test('default values are formatted correctly', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\NoDocblockClass']['methods'];
    $defaultsSig = findFirst($methods, fn ($m) => str_contains($m, 'withDefaults'));

    expect($defaultsSig)->toContain("string \$a = 'hello'")
        ->and($defaultsSig)->toContain('int $b = 42')
        ->and($defaultsSig)->toContain('float $c = 3.14')
        ->and($defaultsSig)->toContain('bool $d = true')
        // BetterReflection renders nullable as union: string|null
        ->and($defaultsSig)->toContain('$e = null')
        ->and($defaultsSig)->toContain('array $f = []');
});

test('variadic parameters use ... syntax', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\VariadicMethod']['methods'];
    $sig = findFirst($methods, fn ($m) => str_contains($m, 'withVariadic'));

    expect($sig)->toContain('string ...$rest');
});

// ===========================================================================
// PHPStan docblock type extraction
// ===========================================================================

test('docblock type overrides native parameter type', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\DocblockRichClass']['methods'];
    $processSig = findFirst($methods, fn ($m) => str_contains($m, 'processItem'));

    // @param array{name: string, age: int, active: bool} $item should replace native 'array'
    expect($processSig)->toContain('array{name: string, age: int, active: bool} $item');
});

test('docblock type overrides native return type', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\DocblockRichClass']['methods'];
    $processSig = findFirst($methods, fn ($m) => str_contains($m, 'processItem'));

    // @return array<string, mixed> should replace native 'array'
    expect($processSig)->toContain('): array<string, mixed>');
});

test('docblock union types appear in parameters', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\DocblockRichClass']['methods'];
    $fetchSig = findFirst($methods, fn ($m) => str_contains($m, 'fetchData'));

    // @param string|resource $source — rendered as (string | resource)
    expect($fetchSig)->toContain('(string | resource) $source');
});

test('@throws are appended to method signature', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\DocblockRichClass']['methods'];
    $processSig = findFirst($methods, fn ($m) => str_contains($m, 'processItem'));

    // phpdoc-parser preserves leading backslash from docblock
    expect($processSig)->toContain('@throws \\InvalidArgumentException|\\RuntimeException');
});

test('@param description is appended as inline comment', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\DocblockRichClass']['methods'];
    $chmodSig = findFirst($methods, fn ($m) => str_contains($m, 'chmod'));

    expect($chmodSig)->toContain('/*The file mode (octal, e.g. 0755)*/')
        ->and($chmodSig)->toContain('/*Whether to apply recursively*/');
});

test('@var type overrides native property type', function () {
    $map = extractFixtures();
    $props = $map['TestFixtures\\DocblockRichClass']['properties'];
    $propsStr = implode("\n", $props);

    expect($propsStr)->toContain('array<string, mixed> $config')
        ->and($propsStr)->toContain('list<string> $tags');
});

test('native types are used when no docblock type present', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\DocblockRichClass']['methods'];
    $simpleSig = findFirst($methods, fn ($m) => str_contains($m, 'simpleMethod'));

    expect($simpleSig)->toContain('string $name')
        ->and($simpleSig)->toContain('): string');
});

// ===========================================================================
// Malformed and missing docblocks
// ===========================================================================

test('malformed docblocks do not crash extraction', function () {
    $map = extractFixtures();

    expect($map)->toHaveKey('TestFixtures\\MalformedDocblock');

    $methods = $map['TestFixtures\\MalformedDocblock']['methods'];
    expect($methods)->toBeArray()
        ->and(count($methods))->toBeGreaterThanOrEqual(3);
});

test('methods without docblocks use native types', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\MalformedDocblock']['methods'];
    $noDocSig = findFirst($methods, fn ($m) => str_contains($m, 'noDocblock'));

    expect($noDocSig)->toContain('string $arg')
        ->and($noDocSig)->toContain('): string');
});

test('class with no docblocks still extracts correctly', function () {
    $map = extractFixtures();
    $class = $map['TestFixtures\\NoDocblockClass'];

    expect($class)->toHaveKey('methods')
        ->and($class)->toHaveKey('properties')
        ->and($class)->not->toHaveKey('doc');
});

// ===========================================================================
// Properties
// ===========================================================================

test('extracts public properties', function () {
    $map = extractFixtures();
    $props = $map['TestFixtures\\SimpleClass']['properties'];
    $str = implode("\n", $props);

    expect($str)->toContain('string $name')
        ->and($str)->toContain('int $count');
});

test('skips protected/private properties in public-only mode', function () {
    $map = extractFixtures();
    $props = $map['TestFixtures\\SimpleClass']['properties'];
    $str = implode("\n", $props);

    expect($str)->not->toContain('$secret')
        ->and($str)->not->toContain('$internal');
});

test('includes protected/private properties when publicOnly is false', function () {
    $map = extractFixtures(publicOnly: false);
    $props = $map['TestFixtures\\SimpleClass']['properties'];
    $str = implode("\n", $props);

    expect($str)->toContain('$secret')
        ->and($str)->toContain('$internal');
});

test('static property is prefixed with "static"', function () {
    $map = extractFixtures();
    $props = $map['TestFixtures\\NoDocblockClass']['properties'];
    $staticProp = findFirst($props, fn ($p) => str_contains($p, '$counter'));

    expect($staticProp)->toStartWith('static ');
});

test('readonly property is prefixed with "readonly"', function () {
    $map = extractFixtures();
    $props = $map['TestFixtures\\NoDocblockClass']['properties'];
    $readonlyProp = findFirst($props, fn ($p) => str_contains($p, '$id'));

    expect($readonlyProp)->toContain('readonly');
});

test('enum auto-generated name/value properties are skipped', function () {
    $map = extractFixtures();
    $enum = $map['TestFixtures\\EnumFixture'];

    expect($enum)->not->toHaveKey('properties');
});

// ===========================================================================
// Constants
// ===========================================================================

test('extracts public constants', function () {
    $map = extractFixtures();
    $consts = $map['TestFixtures\\ConstantsClass']['constants'];

    expect($consts['VERSION'])->toBe('1.0.0')
        ->and($consts['MAX_RETRIES'])->toBe(3)
        ->and($consts['ENABLED'])->toBeTrue()
        ->and($consts['TAGS'])->toBe(['alpha', 'beta', 'gamma']);
});

test('skips protected/private constants in public-only mode', function () {
    $map = extractFixtures();
    $consts = $map['TestFixtures\\ConstantsClass']['constants'];

    expect($consts)->not->toHaveKey('SECRET_KEY')
        ->and($consts)->not->toHaveKey('INTERNAL_FLAG');
});

test('backed enum cases are extracted with values', function () {
    $map = extractFixtures();
    $consts = $map['TestFixtures\\EnumFixture']['constants'];

    expect($consts['Active'])->toBe('active')
        ->and($consts['Inactive'])->toBe('inactive')
        ->and($consts['Pending'])->toBe('pending');
});

test('unit enum cases are listed as values', function () {
    $map = extractFixtures();
    $consts = $map['TestFixtures\\UnitEnumFixture']['constants'];

    expect($consts)->toContain('North')
        ->and($consts)->toContain('South')
        ->and($consts)->toContain('East')
        ->and($consts)->toContain('West');
});

test('compact-enums truncates constants beyond threshold', function () {
    $map = extractFixtures(compactEnumsThreshold: 5);
    $consts = $map['TestFixtures\\ManyConstants']['constants'];

    // Should have 5 real entries + 1 "..." entry
    expect(count($consts))->toBe(6)
        ->and($consts)->toHaveKey('...')
        ->and($consts['...'])->toBe('(8 total)');
});

test('compact-enums does not truncate when below threshold', function () {
    $map = extractFixtures(compactEnumsThreshold: 5);
    $consts = $map['TestFixtures\\ConstantsClass']['constants'];

    // 4 public constants — below threshold of 5
    expect($consts)->not->toHaveKey('...');
});

// ===========================================================================
// Namespace filtering
// ===========================================================================

test('namespace filter includes only matching classes', function () {
    $map = extractFixtures(namespace: 'TestFixtures');

    foreach (array_keys($map) as $fqcn) {
        expect($fqcn)->toStartWith('TestFixtures');
    }
});

test('empty namespace filter includes all classes', function () {
    $gen = new BlueprintGenerator('');
    $map = $gen->extractFromDirectory(fixturesPath());

    expect($map)->toHaveKey('TestFixtures\\SimpleClass');
});

test('skipInternal excludes Internal namespace classes', function () {
    $map = extractFixtures(namespace: 'TestFixtures', skipInternal: true);

    expect($map)->not->toHaveKey('TestFixtures\\Internal\\InternalClass');
});

test('skipInternal false includes Internal namespace classes', function () {
    $map = extractFixtures(namespace: 'TestFixtures', skipInternal: false);

    expect($map)->toHaveKey('TestFixtures\\Internal\\InternalClass');
});

test('excludeNamespaces filters out specified prefixes', function () {
    $map = extractFixtures(
        namespace: 'TestFixtures',
        skipInternal: false,
        excludeNamespaces: ['TestFixtures\\Internal\\'],
    );

    expect($map)->not->toHaveKey('TestFixtures\\Internal\\InternalClass')
        ->and($map)->toHaveKey('TestFixtures\\SimpleClass');
});

// ===========================================================================
// Omit-when-empty
// ===========================================================================

test('empty fields are omitted from output', function () {
    $map = extractFixtures();
    $final = $map['TestFixtures\\FinalFixture'];

    expect($final)->not->toHaveKey('constants')
        ->and($final)->not->toHaveKey('properties')
        ->and($final)->not->toHaveKey('extends')
        ->and($final)->not->toHaveKey('implements')
        ->and($final)->not->toHaveKey('uses');
});

// ===========================================================================
// JSON output format
// ===========================================================================

test('JSON uses unescaped slashes and unicode', function () {
    $gen = new BlueprintGenerator('TestFixtures');
    $gen->extractFromDirectory(fixturesPath());
    $json = $gen->toJson();

    expect($json)->toContain('TestFixtures\\\\SimpleClass');
    expect($json)->not->toContain('\\/');
});

// ===========================================================================
// Extraction resets between calls
// ===========================================================================

test('extractFromDirectory resets state between calls', function () {
    $gen = new BlueprintGenerator('TestFixtures');

    $first = $gen->extractFromDirectory(fixturesPath());
    $second = $gen->extractFromDirectory(fixturesPath());

    expect($first)->toBe($second);
});

// ===========================================================================
// Interface methods
// ===========================================================================

test('interface methods are extracted', function () {
    $map = extractFixtures();
    $iface = $map['TestFixtures\\InterfaceFixture'];

    expect($iface['methods'])->toBeArray()
        ->and(count($iface['methods']))->toBe(2);
});

test('interface omits abstract flag', function () {
    $map = extractFixtures();
    $iface = $map['TestFixtures\\InterfaceFixture'];

    expect($iface)->not->toHaveKey('abstract');
});

// ===========================================================================
// Trait methods
// ===========================================================================

test('trait public methods are extracted', function () {
    $map = extractFixtures();
    $trait = $map['TestFixtures\\TraitFixture'];
    $sigs = implode("\n", $trait['methods']);

    expect($sigs)->toContain('log(');
});

test('trait protected methods are skipped in public-only mode', function () {
    $map = extractFixtures(publicOnly: true);
    $trait = $map['TestFixtures\\TraitFixture'];
    $sigs = implode("\n", $trait['methods']);

    expect($sigs)->not->toContain('debug(');
});

// ===========================================================================
// Return types
// ===========================================================================

test('return type is included in method signature', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $getSig = findFirst($methods, fn ($m) => str_contains($m, 'getName'));

    expect($getSig)->toContain('): string');
});

test('void return type is included', function () {
    $map = extractFixtures();
    $methods = $map['TestFixtures\\SimpleClass']['methods'];
    $setSig = findFirst($methods, fn ($m) => str_contains($m, 'setName'));

    expect($setSig)->toContain('): void');
});
