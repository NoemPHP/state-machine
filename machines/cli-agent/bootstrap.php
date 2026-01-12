<?php
/**
 * CLI Agent Bootstrap
 * Minimal infrastructure for CLI interface
 */

declare(strict_types=1);

// ============================================================================
// Autoloader
// ============================================================================

spl_autoload_register(function (string $class): void {
    // Handle CliAgent namespace
    if (str_starts_with($class, 'CliAgent\\')) {
        $relativePath = str_replace('CliAgent\\', '', $class);
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativePath) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// ============================================================================
// Path Helpers
// ============================================================================

if (!function_exists('cli_agent_path')) {
    /**
     * Get path relative to cli-agent directory
     */
    function cli_agent_path(string $path): string
    {
        return __DIR__ . '/' . ltrim($path, '/');
    }
}

if (!function_exists('machines_path')) {
    /**
     * Get path to a machine directory
     *
     * Shared helper used by multiple machines. Uses function_exists() check
     * to prevent redeclaration errors when machines summon each other.
     *
     * @param string $machineName Name of the machine (e.g., 'machine-agent')
     * @param string $file Optional file within the machine directory
     * @return string Absolute path to machine or file
     */
    function machines_path(string $machineName, string $file = ''): string
    {
        $basePath = dirname(__DIR__) . '/' . $machineName;
        return $file ? $basePath . '/' . ltrim($file, '/') : $basePath;
    }
}
