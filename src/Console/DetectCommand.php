<?php

namespace Upgrader\Console;

use Upgrader\Services\ModuleLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'detect')]
class DetectCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Auto-detect modules applicable to a project')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to project', getcwd());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $path = $input->getOption('path');

        if (!is_dir($path)) {
            $io->error("Invalid project path: {$path}");
            return Command::FAILURE;
        }

        $io->title('Project Analysis');
        $io->text("Analyzing: <info>{$path}</info>");
        $io->newLine();

        $loader = new ModuleLoader();
        $registry = $loader->loadAllModules();

        $detected = $registry->detectModules($path);

        if (empty($detected)) {
            $io->warning('No applicable modules detected');
            return Command::SUCCESS;
        }

        $io->section('Detected Modules');

        $rows = [];
        foreach ($detected as $moduleName => $info) {
            $module = $info['module'];
            $currentVersion = $info['current_version'];
            $availableVersions = $info['available_versions'];
            $latest = end($availableVersions);

            $rows[] = [
                $moduleName,
                $currentVersion ?: 'Not detected',
                $latest,
                $currentVersion && $currentVersion !== $latest ? '⚠ Upgrade available' : '✓ Latest',
            ];
        }

        $io->table(
            ['Module', 'Current Version', 'Latest Version', 'Status'],
            $rows
        );

        // Show dependency relationships
        $io->section('Module Dependencies');

        foreach ($detected as $moduleName => $info) {
            $module = $info['module'];
            $dependencies = $module->getDependencies();

            if (!empty($dependencies)) {
                $io->text("<info>{$moduleName}</info> depends on:");
                foreach ($dependencies as $dep) {
                    $hasDepModule = isset($detected[$dep]);
                    $status = $hasDepModule ? '✓' : '✗';
                    $io->text("  {$status} {$dep}");
                }
                $io->newLine();
            }
        }

        // Suggest upgrade commands
        $io->section('Suggested Upgrade Commands');

        foreach ($detected as $moduleName => $info) {
            $currentVersion = $info['current_version'];
            $availableVersions = $info['available_versions'];
            $latest = end($availableVersions);

            if ($currentVersion && $currentVersion !== $latest) {
                $io->text("upgrader upgrade --module={$moduleName} --from={$currentVersion} --to={$latest} --path={$path}");
            }
        }

        return Command::SUCCESS;
    }
}
