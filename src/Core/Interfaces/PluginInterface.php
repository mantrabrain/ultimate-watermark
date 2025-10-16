<?php

namespace MantraBrain\UltimateWatermark\Core\Interfaces;

/**
 * Plugin Interface
 * 
 * Defines the contract for the main plugin class
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
interface PluginInterface
{
    /**
     * Get plugin version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get plugin directory path
     *
     * @return string
     */
    public function getPluginDir(): string;

    /**
     * Get plugin URL
     *
     * @return string
     */
    public function getPluginUrl(): string;
}
