<?php

declare(strict_types=1);

namespace Blueprint\Command;

use Blueprint\BlueprintGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'extract',
    description: 'Generate a JSON blueprint of a PHP library\'s class signatures',
)]
class ExtractCommand extends Command
{
    private const CONFIG_FILENAME = 'blueprint.config.php';

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Directory to scan for PHP files')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output JSON file path (default: blueprint.json in working directory)', 'blueprint.json')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Filter by namespace prefix (e.g. "Vendor\\Package")', '')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exclude namespace prefix (repeatable)', [])
            ->addOption('include-private', null, InputOption::VALUE_NONE, 'Include private/protected members')
            ->addOption('include-internal', null, InputOption::VALUE_NONE, 'Include \\Internal\\ namespace classes')
            ->addOption('short-docs', null, InputOption::VALUE_NONE, 'Truncate doc summaries to first sentence')
            ->addOption('compact-enums', null, InputOption::VALUE_NONE, 'Truncate large constant/enum lists (>5 entries)')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: json, toon (experimental), or both', 'json')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('no-config', null, InputOption::VALUE_NONE, 'Ignore config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Load config file unless --no-config
        $config = [];
        if (!$input->getOption('no-config')) {
            $config = $this->loadConfig($input, $output);
            if ($config === null) {
                return Command::FAILURE;
            }
        }

        // Resolve path: CLI arg > config > error
        $path = $input->getArgument('path') ?? ($config['path'] ?? null);
        if ($path === null) {
            $output->writeln('<error>No path provided. Pass a directory argument or set "path" in '.self::CONFIG_FILENAME.'.</error>');

            return Command::FAILURE;
        }
        $libraryPath = Path::canonicalize($path);

        if (!is_dir($libraryPath)) {
            $output->writeln("<error>Directory not found: {$libraryPath}</error>");

            return Command::FAILURE;
        }

        // Merge options: CLI wins over config, config wins over defaults
        $outputPath   = $this->resolve($input, 'output', $config, 'blueprint.json');
        $namespace    = $this->resolve($input, 'namespace', $config, '');
        $includePriv  = $this->resolveFlag($input, 'include-private', $config);
        $includeInt   = $this->resolveFlag($input, 'include-internal', $config);
        $shortDocs    = $this->resolveFlag($input, 'short-docs', $config);
        $compactEnums = $this->resolveFlag($input, 'compact-enums', $config);
        $format       = $this->resolve($input, 'format', $config, 'json');

        $validFormats = ['json', 'toon', 'both'];
        if (!in_array($format, $validFormats, true)) {
            $output->writeln('<error>Invalid --format value: "'.$format.'". Must be one of: '.implode(', ', $validFormats).'</error>');

            return Command::FAILURE;
        }

        // When format is toon-only and the user didn't explicitly set -o, swap the default extension
        if ($format === 'toon' && $outputPath === 'blueprint.json') {
            $outputPath = 'blueprint.toon';
        }

        // Merge exclude lists from CLI and config
        /** @var list<string> $cliExclude */
        $cliExclude    = $input->getOption('exclude');
        $configExclude = $config['exclude'] ?? [];
        $exclude       = array_values(array_unique(array_merge($configExclude, $cliExclude)));

        $generator = new BlueprintGenerator(
            $namespace,
            !$includePriv,
            !$includeInt,
            $shortDocs,
            $compactEnums ? 5 : 0,
            $exclude,
        );
        $apiMap = $generator->extractFromDirectory($libraryPath);

        $resolvedOutput = Path::canonicalize($outputPath);
        $generator->saveToFile($resolvedOutput, $format);

        $classCount = count($apiMap);

        if ($format === 'both') {
            $jsonPath = preg_replace('/\.toon$/', '.json', $resolvedOutput);
            $toonPath = preg_replace('/\.json$/', '.toon', $resolvedOutput);
            $jsonSize = $this->formatSize(filesize($jsonPath));
            $toonSize = $this->formatSize(filesize($toonPath));
            $output->writeln("Extracted {$classCount} classes → {$jsonPath} ({$jsonSize}, JSON) + {$toonPath} ({$toonSize}, TOON)");
        } else {
            $size = $this->formatSize(filesize($resolvedOutput));
            $output->writeln("Extracted {$classCount} classes → {$resolvedOutput} ({$size}, ".strtoupper($format).')');
        }

        return Command::SUCCESS;
    }

    /**
     * Load and validate the config file.
     *
     * @return array<string, mixed>|null  Config array, empty array if no file found, or null on error
     */
    private function loadConfig(InputInterface $input, OutputInterface $output): ?array
    {
        $configPath = $input->getOption('config');

        if ($configPath !== null) {
            // Explicit --config: must exist
            $configPath = Path::canonicalize($configPath);
            if (!is_file($configPath)) {
                $output->writeln("<error>Config file not found: {$configPath}</error>");

                return null;
            }
        } else {
            // Auto-discover in cwd
            $configPath = Path::canonicalize(self::CONFIG_FILENAME);
            if (!is_file($configPath)) {
                return [];
            }
        }

        $config = require $configPath;
        if (!is_array($config)) {
            $output->writeln("<error>Config file must return an array: {$configPath}</error>");

            return null;
        }

        $output->writeln("Using config: {$configPath}", OutputInterface::VERBOSITY_VERBOSE);

        return $config;
    }

    /**
     * Resolve a VALUE_REQUIRED option: CLI value wins if it differs from the declared default.
     *
     * @param array<string, mixed> $config
     */
    private function resolve(InputInterface $input, string $option, array $config, string $default): string
    {
        $cliValue = $input->getOption($option);
        if ($cliValue !== $default) {
            return $cliValue;
        }

        $configKey = $option;
        if (isset($config[$configKey])) {
            return (string) $config[$configKey];
        }

        return $default;
    }

    /**
     * Resolve a VALUE_NONE (boolean flag) option: CLI flag wins if set, else config value.
     *
     * @param array<string, mixed> $config
     */
    private function resolveFlag(InputInterface $input, string $option, array $config): bool
    {
        if ($input->getOption($option)) {
            return true;
        }

        $configKey = $option;

        return (bool) ($config[$configKey] ?? false);
    }

    private function formatSize(int|false $bytes): string
    {
        if ($bytes === false) {
            return '0B';
        }

        return $bytes > 1024 ? round($bytes / 1024, 1).'KB' : $bytes.'B';
    }
}
