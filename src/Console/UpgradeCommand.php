<?php

declare(strict_types=1);

namespace Upgrader\Console;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Upgrader\Services\ModuleLoader;
use Upgrader\Services\UpgradeOrchestrator;

#[AsCommand(name: 'upgrade')]
class UpgradeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Upgrade project modules to target versions')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module to upgrade (php, laravel, reactjs, vuejs, etc.)')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Source version')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target version')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to project', getcwd())
            ->addOption('with-dependencies', null, InputOption::VALUE_NONE, 'Also upgrade module dependencies')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Perform dry run without making changes')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Modular Upgrader');

        $path = $input->getOption('path');
        $moduleName = $input->getOption('module');
        $from = $input->getOption('from');
        $to = $input->getOption('to');
        $withDependencies = $input->getOption('with-dependencies');
        $dryRun = $input->getOption('dry-run');

        // Load modules
        $loader = new ModuleLoader;
        $registry = $loader->loadAllModules();

        // Auto-detect if no module specified
        if (! $moduleName) {
            $io->text('No module specified, auto-detecting...');
            $detected = $registry->detectModules($path);

            if (empty($detected)) {
                $io->error('No modules detected in project');

                return Command::FAILURE;
            }

            if (count($detected) === 1) {
                $moduleName = array_key_first($detected);
                $io->text("Detected module: <info>{$moduleName}</info>");
            } else {
                $moduleName = $io->choice(
                    'Multiple modules detected. Which one to upgrade?',
                    array_keys($detected)
                );
            }
        }

        $module = $registry->getModule($moduleName);
        if (! $module) {
            $io->error("Module not found: {$moduleName}");

            return Command::FAILURE;
        }

        // Auto-detect versions if not specified
        if (! $from) {
            $from = $module->detectCurrentVersion($path);
            if ($from) {
                $io->text("Detected current version: <info>{$from}</info>");
            } else {
                $io->error('Could not detect current version');

                return Command::FAILURE;
            }
        }

        if (! $to) {
            $availableVersions = $module->getAvailableVersions();
            $to = $io->choice(
                'Select target version',
                $availableVersions,
                end($availableVersions)
            );
        }

        // Load configuration
        $config = $this->loadConfiguration($input, $io);

        // Initialize module
        $registry->initializeModule($moduleName, $config);

        // Create orchestrator
        $orchestrator = new UpgradeOrchestrator($registry, $io);

        if ($dryRun) {
            $io->note('DRY RUN MODE - No changes will be made');
        }

        try {
            $result = $orchestrator->upgrade(
                $moduleName,
                $path,
                $from,
                $to,
                [
                    'with_dependencies' => $withDependencies,
                    'dry_run' => $dryRun,
                ]
            );

            if ($result['success']) {
                $io->success('Upgrade completed successfully!');

                if (! empty($result['summary'])) {
                    $io->section('Upgrade Summary');
                    $io->listing($result['summary']);
                }

                if (! empty($result['manual_steps'])) {
                    $io->section('Manual Steps Required');
                    $io->warning($result['manual_steps']);
                }

                return Command::SUCCESS;
            }
            $io->error('Upgrade failed: ' . ($result['error'] ?? 'Unknown error'));

            return Command::FAILURE;

        } catch (Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function loadConfiguration(InputInterface $input, SymfonyStyle $io): array
    {
        $configFile = $input->getOption('config');

        if (! $configFile) {
            $configFile = getcwd() . '/.upgrader.yml';
        }

        if (! file_exists($configFile)) {
            return [];
        }

        $io->text("Loading configuration from: {$configFile}");

        return \Symfony\Component\Yaml\Yaml::parseFile($configFile);
    }
}
