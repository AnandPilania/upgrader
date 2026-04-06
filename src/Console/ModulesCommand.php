<?php

declare(strict_types=1);

namespace Upgrader\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Upgrader\Services\ModuleLoader;

#[AsCommand(name: 'modules')]
class ModulesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('List all available upgrade modules and their dependencies');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Available Upgrade Modules');

        $loader = new ModuleLoader;
        $registry = $loader->loadAllModules();

        $modules = $registry->getAll();

        if (empty($modules)) {
            $io->warning('No modules found');

            return Command::SUCCESS;
        }

        // Display modules table
        $rows = [];
        foreach ($modules as $module) {
            $rows[] = [
                $module->getName(),
                $module->getVersion(),
                $module->getDescription(),
                implode(', ', $module->getDependencies()) ?: 'None',
                implode(', ', $module->getAvailableVersions()),
            ];
        }

        $io->table(
            ['Module', 'Version', 'Description', 'Dependencies', 'Supported Versions'],
            $rows
        );

        // Display dependency graph
        $io->section('Dependency Graph');
        $graph = $registry->getDependencyGraph();

        foreach ($graph as $module => $dependencies) {
            if (empty($dependencies)) {
                $io->text("├─ <info>{$module}</info> (no dependencies)");
            } else {
                $io->text("├─ <info>{$module}</info> depends on:");
                foreach ($dependencies as $dep) {
                    $io->text("│  └─ <comment>{$dep}</comment>");
                }
            }
        }

        // Validate dependencies
        $errors = $registry->validateDependencies();
        if (! empty($errors)) {
            $io->section('Dependency Issues');
            $io->error($errors);
        } else {
            $io->success('All module dependencies are satisfied');
        }

        return Command::SUCCESS;
    }
}
