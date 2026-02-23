<?php

declare(strict_types=1);

use Blueprint\Command\ExtractCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

function createCommandTester(): CommandTester
{
    $app = new Application();
    $app->addCommand(new ExtractCommand());
    $command = $app->find('extract');

    return new CommandTester($command);
}

// ===========================================================================
// Basic command execution
// ===========================================================================

test('command succeeds with valid path', function () {
    $tester = createCommandTester();
    $output = tempnam(sys_get_temp_dir(), 'bp_cmd_');

    $tester->execute([
        'path'        => fixturesPath(),
        '--namespace' => 'TestFixtures',
        '--output'    => $output,
        '--no-config' => true,
    ]);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('Extracted');

    $data = json_decode(file_get_contents($output), true);
    expect($data)->toBeArray()
        ->and($data)->toHaveKey('TestFixtures\\SimpleClass');

    unlink($output);
});

test('command fails with non-existent directory', function () {
    $tester = createCommandTester();

    $tester->execute([
        'path'        => '/nonexistent/path/here',
        '--no-config' => true,
    ]);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('Directory not found');
});

test('command fails with no path and no config', function () {
    $tester = createCommandTester();

    // Run from a temp dir with no config file
    $cwd = getcwd();
    chdir(sys_get_temp_dir());

    $tester->execute([
        '--no-config' => true,
    ]);

    chdir($cwd);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('No path provided');
});

// ===========================================================================
// CLI options
// ===========================================================================

test('--namespace filters output', function () {
    $tester = createCommandTester();
    $output = tempnam(sys_get_temp_dir(), 'bp_ns_');

    $tester->execute([
        'path'        => fixturesPath(),
        '--namespace' => 'TestFixtures\\Internal',
        '--output'    => $output,
        '--no-config' => true,
    ]);

    $data = json_decode(file_get_contents($output), true);
    expect($data)->toHaveKey('TestFixtures\\Internal\\InternalClass')
        ->and($data)->not->toHaveKey('TestFixtures\\SimpleClass');

    unlink($output);
});

test('--include-private includes non-public members', function () {
    $tester = createCommandTester();
    $output = tempnam(sys_get_temp_dir(), 'bp_priv_');

    $tester->execute([
        'path'              => fixturesPath(),
        '--namespace'       => 'TestFixtures',
        '--output'          => $output,
        '--include-private' => true,
        '--no-config'       => true,
    ]);

    $data    = json_decode(file_get_contents($output), true);
    $methods = implode("\n", $data['TestFixtures\\SimpleClass']['methods']);

    expect($methods)->toContain('protectedMethod')
        ->and($methods)->toContain('privateMethod');

    unlink($output);
});

test('--short-docs truncates summaries', function () {
    $tester = createCommandTester();
    $output = tempnam(sys_get_temp_dir(), 'bp_short_');

    $tester->execute([
        'path'         => fixturesPath(),
        '--namespace'  => 'TestFixtures',
        '--output'     => $output,
        '--short-docs' => true,
        '--no-config'  => true,
    ]);

    expect($tester->getStatusCode())->toBe(0);

    $data = json_decode(file_get_contents($output), true);
    expect($data)->toBeArray();

    unlink($output);
});

test('--exclude filters out namespace prefixes', function () {
    $tester = createCommandTester();
    $output = tempnam(sys_get_temp_dir(), 'bp_excl_');

    $tester->execute([
        'path'               => fixturesPath(),
        '--namespace'        => 'TestFixtures',
        '--include-internal' => true,
        '--exclude'          => ['TestFixtures\\Internal\\'],
        '--output'           => $output,
        '--no-config'        => true,
    ]);

    $data = json_decode(file_get_contents($output), true);
    expect($data)->not->toHaveKey('TestFixtures\\Internal\\InternalClass')
        ->and($data)->toHaveKey('TestFixtures\\SimpleClass');

    unlink($output);
});

test('--compact-enums truncates large constant lists', function () {
    $tester = createCommandTester();
    $output = tempnam(sys_get_temp_dir(), 'bp_compact_');

    $tester->execute([
        'path'            => fixturesPath(),
        '--namespace'     => 'TestFixtures',
        '--output'        => $output,
        '--compact-enums' => true,
        '--no-config'     => true,
    ]);

    $data   = json_decode(file_get_contents($output), true);
    $consts = $data['TestFixtures\\ManyConstants']['constants'];

    expect($consts)->toHaveKey('...');

    unlink($output);
});

// ===========================================================================
// Config file
// ===========================================================================

test('command reads config file when present', function () {
    $tmpDir = sys_get_temp_dir().'/bp_config_test_'.uniqid();
    mkdir($tmpDir, 0755, true);

    // Write a config file
    $configContent = "<?php\nreturn [\n    'path' => '".addslashes(fixturesPath())."',\n    'namespace' => 'TestFixtures',\n    'output' => '{$tmpDir}/out.json',\n];\n";
    file_put_contents($tmpDir.'/.blueprint.config.php', $configContent);

    $tester = createCommandTester();
    $cwd    = getcwd();
    chdir($tmpDir);

    $tester->execute([]);

    chdir($cwd);

    expect($tester->getStatusCode())->toBe(0);
    expect(file_exists($tmpDir.'/out.json'))->toBeTrue();

    // Clean up
    unlink($tmpDir.'/out.json');
    unlink($tmpDir.'/.blueprint.config.php');
    rmdir($tmpDir);
});

test('--config loads explicit config file', function () {
    $tmpDir = sys_get_temp_dir().'/bp_explicit_config_'.uniqid();
    mkdir($tmpDir, 0755, true);

    $configPath = $tmpDir.'/custom.config.php';
    $outputPath = $tmpDir.'/result.json';

    $configContent = "<?php\nreturn [\n    'path' => '".addslashes(fixturesPath())."',\n    'namespace' => 'TestFixtures',\n    'output' => '".addslashes($outputPath)."',\n];\n";
    file_put_contents($configPath, $configContent);

    $tester = createCommandTester();
    $tester->execute([
        '--config' => $configPath,
    ]);

    expect($tester->getStatusCode())->toBe(0);
    expect(file_exists($outputPath))->toBeTrue();

    // Clean up
    unlink($outputPath);
    unlink($configPath);
    rmdir($tmpDir);
});

test('--config with non-existent file fails', function () {
    $tester = createCommandTester();

    $tester->execute([
        '--config' => '/nonexistent/config.php',
    ]);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('Config file not found');
});

test('--no-config ignores config file', function () {
    $tmpDir = sys_get_temp_dir().'/bp_noconfig_test_'.uniqid();
    mkdir($tmpDir, 0755, true);

    // Write a config that would set path — but --no-config should ignore it
    $configContent = "<?php\nreturn ['path' => '".addslashes(fixturesPath())."'];\n";
    file_put_contents($tmpDir.'/.blueprint.config.php', $configContent);

    $tester = createCommandTester();
    $cwd    = getcwd();
    chdir($tmpDir);

    $tester->execute([
        '--no-config' => true,
        // No path argument — should fail because config is ignored
    ]);

    chdir($cwd);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('No path provided');

    // Clean up
    unlink($tmpDir.'/.blueprint.config.php');
    rmdir($tmpDir);
});

// ===========================================================================
// Output reporting
// ===========================================================================

test('command reports class count and file size', function () {
    $tester = createCommandTester();
    $output = tempnam(sys_get_temp_dir(), 'bp_report_');

    $tester->execute([
        'path'        => fixturesPath(),
        '--namespace' => 'TestFixtures',
        '--output'    => $output,
        '--no-config' => true,
    ]);

    $display = $tester->getDisplay();
    // Should say something like "Extracted 15 classes → /path/to/file (XKB)"
    expect($display)->toMatch('/Extracted \d+ classes/');

    unlink($output);
});
