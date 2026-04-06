<?php

namespace Upgrader\Core;

/**
 * Interface for modules that can perform version upgrades
 */
interface UpgradeModuleInterface extends ModuleInterface
{
    /**
     * Analyze project for upgrade compatibility
     * 
     * @param string $projectPath Path to project
     * @param string $targetVersion Target version to upgrade to
     * @return array Analysis results
     */
    public function analyze(string $projectPath, string $targetVersion): array;

    /**
     * Perform upgrade from one version to another
     * 
     * @param string $projectPath Path to project
     * @param string $fromVersion Source version
     * @param string $toVersion Target version
     * @return array Upgrade results
     */
    public function upgrade(string $projectPath, string $fromVersion, string $toVersion): array;

    /**
     * Get transformers for specific version upgrade
     * 
     * @param string $fromVersion Source version
     * @param string $toVersion Target version
     * @return array<TransformerInterface>
     */
    public function getTransformers(string $fromVersion, string $toVersion): array;

    /**
     * Validate upgrade is possible
     * 
     * @param string $projectPath Path to project
     * @param string $fromVersion Source version
     * @param string $toVersion Target version
     * @return bool True if upgrade is possible
     */
    public function canUpgrade(string $projectPath, string $fromVersion, string $toVersion): bool;

    /**
     * Get upgrade path between versions
     * 
     * @param string $fromVersion Source version
     * @param string $toVersion Target version
     * @return array Ordered array of versions to upgrade through
     */
    public function getUpgradePath(string $fromVersion, string $toVersion): array;

    /**
     * Get breaking changes between versions
     * 
     * @param string $fromVersion Source version
     * @param string $toVersion Target version
     * @return array Breaking changes
     */
    public function getBreakingChanges(string $fromVersion, string $toVersion): array;
}
