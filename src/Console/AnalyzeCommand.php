<?php

namespace Upgrader\Console;

use Upgrader\Services\ModuleLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'analyze')]
class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Analyze project for upgrade compatibility')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module to analyze')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Target version')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to project', getcwd())
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table|json|yaml)', 'table')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Project Analysis');

        $path = $input->getOption('path');
        $moduleName = $input->getOption('module');
        $target = $input->getOption('target');
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output');

        // Load modules
        $loader = new ModuleLoader();
        $registry = $loader->loadAllModules();

        // Auto-detect if no module specified
        if (!$moduleName) {
            $io->text('No module specified, analyzing all detected modules...');
            $detected = $registry->detectModules($path);

            if (empty($detected)) {
                $io->error('No modules detected in project');
                return Command::FAILURE;
            }

            $results = [];
            foreach ($detected as $name => $info) {
                $module = $info['module'];
                $currentVersion = $info['current_version'];

                if ($currentVersion) {
                    $targetVersion = $target ?? end($info['available_versions']);
                    $results[$name] = $module->analyze($path, $targetVersion);
                }
            }

            $this->displayResults($io, $results, $format, $outputFile);
            return Command::SUCCESS;
        }

        // Single module analysis
        $module = $registry->getModule($moduleName);
        if (!$module) {
            $io->error("Module not found: {$moduleName}");
            return Command::FAILURE;
        }

        if (!$module->canHandle($path)) {
            $io->warning("Module '{$moduleName}' is not applicable to this project");
            return Command::SUCCESS;
        }

        $currentVersion = $module->detectCurrentVersion($path);
        if (!$currentVersion) {
            $io->error('Could not detect current version');
            return Command::FAILURE;
        }

        if (!$target) {
            $availableVersions = $module->getAvailableVersions();
            $target = end($availableVersions);
        }

        $io->text("Analyzing {$moduleName} upgrade from {$currentVersion} to {$target}");

        $analysis = $module->analyze($path, $target);

        $this->displayResults($io, [$moduleName => $analysis], $format, $outputFile);

        return Command::SUCCESS;
    }

    private function displayResults(SymfonyStyle $io, array $results, string $format, ?string $outputFile): void
    {
        if ($format === 'json') {
            $content = json_encode($results, JSON_PRETTY_PRINT);
        } elseif ($format === 'yaml') {
            $content = \Symfony\Component\Yaml\Yaml::dump($results, 4, 2);
        } else {
            $this->displayTable($io, $results);
            return;
        }

        if ($outputFile) {
            file_put_contents($outputFile, $content);
            $io->success("Analysis saved to: {$outputFile}");
        } else {
            $io->text($content);
        }
    }

    private function displayTable(SymfonyStyle $io, array $results): void
    {
        foreach ($results as $moduleName => $analysis) {
            $io->section("Module: {$moduleName}");

            $io->table(
                ['Metric', 'Value'],
                [
                    ['Current Version', $analysis['current_version'] ?? 'Unknown'],
                    ['Target Version', $analysis['target_version'] ?? 'Unknown'],
                ]
            );

            if (!empty($analysis['breaking_changes'])) {
                $io->section('Breaking Changes');
                $io->listing($analysis['breaking_changes']);
            }

            if (!empty($analysis['deprecated_features'])) {
                $io->section('Deprecated Features');
                $io->listing($analysis['deprecated_features']);
            }

            if (!empty($analysis['new_features'])) {
                $io->section('New Features');
                $io->listing($analysis['new_features']);
            }

            // Module-specific information
            if (isset($analysis['language_analysis'])) {
                $io->section('Language Upgrade Required');
                $io->text("Module: {$analysis['language_module']}");
            }

            if (isset($analysis['frontend_framework'])) {
                $io->text("Frontend Framework: {$analysis['frontend_framework']}");
            }

            $io->newLine();
        }
    }
}
