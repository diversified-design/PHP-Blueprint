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
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Directory to scan for PHP files')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output JSON file path (default: blueprint.json in working directory)', 'blueprint.json')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Filter by namespace prefix (e.g. "Vendor\\Package")', '')
            ->addOption('include-private', null, InputOption::VALUE_NONE, 'Include private/protected members')
            ->addOption('include-internal', null, InputOption::VALUE_NONE, 'Include \\Internal\\ namespace classes')
            ->addOption('short-docs', null, InputOption::VALUE_NONE, 'Truncate doc summaries to first sentence')
            ->addOption('compact-enums', null, InputOption::VALUE_NONE, 'Truncate large constant/enum lists (>5 entries)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $libraryPath = Path::canonicalize($input->getArgument('path'));

        if (!is_dir($libraryPath)) {
            $output->writeln("<error>Directory not found: {$libraryPath}</error>");

            return Command::FAILURE;
        }

        $namespace    = $input->getOption('namespace');
        $publicOnly   = !$input->getOption('include-private');
        $skipInternal = !$input->getOption('include-internal');
        $shortDocs    = $input->getOption('short-docs');
        $compactEnums = $input->getOption('compact-enums') ? 5 : 0;

        $extractor = new BlueprintGenerator($namespace, $publicOnly, $skipInternal, $shortDocs, $compactEnums);
        $apiMap    = $extractor->extractFromDirectory($libraryPath);

        $outputPath = Path::canonicalize($input->getOption('output'));
        $extractor->saveToFile($outputPath);

        $size = filesize($outputPath);
        $unit = $size > 1024 ? round($size / 1024, 1).'KB' : $size.'B';
        $output->writeln("Extracted ".count($apiMap)." classes → {$outputPath} ({$unit})");

        return Command::SUCCESS;
    }
}
