<?php

declare(strict_types=1);

/**
 * Machine Agent Bootstrap
 *
 * Provides infrastructure setup for machine-agent:
 * - PSR-4 autoloader for custom classes
 * - Path resolution helpers
 * - Error handling configuration
 *
 * Include this in holon.yml to offload infrastructure concerns
 * from business logic.
 */

// ============================================================================
// PSR-4 AUTOLOADER
// ============================================================================

spl_autoload_register(function (string $class): void {
    // Base namespace for machine-specific classes
    $namespace = 'MachineAgent\\';
    $baseDir = __DIR__ . '/src/';

    // Check if class uses this namespace
    $len = strlen($namespace);
    if (strncmp($namespace, $class, $len) !== 0) {
        return;
    }

    // Get relative class name
    $relativeClass = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Load the file if it exists
    if (file_exists($file)) {
        require $file;
    }
});

// ============================================================================
// PATH HELPERS
// ============================================================================

if (!function_exists('machine_path')) {
    /**
     * Resolve machine-relative path
     *
     * @param string $path Path relative to machine directory
     * @return string Absolute path
     */
    function machine_path(string $path): string
    {
        return __DIR__ . '/' . ltrim($path, '/');
    }
}

if (!function_exists('machines_path')) {
    /**
     * Resolve path to another machine
     *
     * @param string $machineName Machine name (kebab-case)
     * @param string $file Optional file within machine directory
     * @return string Absolute path
     */
    function machines_path(string $machineName, string $file = ''): string
    {
        $basePath = dirname(__DIR__) . '/' . $machineName;
        return $file ? $basePath . '/' . ltrim($file, '/') : $basePath;
    }
}

// ============================================================================
// YAML HELPERS
// ============================================================================

if (!function_exists('load_yaml')) {
    /**
     * Load and parse YAML file with error handling
     *
     * @param string $path Absolute or machine-relative path
     * @return array<string, mixed> Parsed YAML content
     * @throws RuntimeException If file cannot be loaded or parsed
     */
    function load_yaml(string $path): array
    {
        // Convert relative paths to absolute
        if (!str_starts_with($path, '/')) {
            $path = machine_path($path);
        }

        if (!file_exists($path)) {
            throw new RuntimeException("YAML file not found: {$path}");
        }

        $content = yaml_parse_file($path);

        if ($content === false) {
            throw new RuntimeException("Failed to parse YAML file: {$path}");
        }

        return $content;
    }
}

// ============================================================================
// ERROR HANDLING
// ============================================================================

if (!function_exists('configure_error_handling')) {
    /**
     * Configure error display for development
     *
     * Sets appropriate error reporting for machine development.
     * Errors are visible but don't halt execution unnecessarily.
     */
    function configure_error_handling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
    }
}

// ============================================================================
// MACHINE GENERATION HELPERS
// ============================================================================

if (!function_exists('ensure_machine_directory')) {
    /**
     * Ensure machine directory exists with proper permissions
     *
     * @param string $machineName Machine name (kebab-case)
     * @return string Absolute path to machine directory
     * @throws RuntimeException If directory cannot be created
     */
    function ensure_machine_directory(string $machineName): string
    {
        $path = machines_path($machineName);

        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new RuntimeException("Failed to create machine directory: {$path}");
            }
        }

        return $path;
    }
}

if (!function_exists('atomic_write')) {
    /**
     * Write file with atomic operation
     *
     * Writes to temporary file first, then renames to avoid partial writes.
     *
     * @param string $path Absolute file path
     * @param string $content File content
     * @throws RuntimeException If write fails
     */
    function atomic_write(string $path, string $content): void
    {
        $tempFile = $path . '.tmp.' . uniqid();

        try {
            if (file_put_contents($tempFile, $content) === false) {
                throw new RuntimeException("Failed to write temporary file: {$tempFile}");
            }

            if (!rename($tempFile, $path)) {
                throw new RuntimeException("Failed to rename temporary file to: {$path}");
            }
        } finally {
            // Cleanup temp file if something went wrong
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}

if (!function_exists('is_valid_machine_name')) {
    /**
     * Validate machine name format (kebab-case)
     *
     * @param string $name Machine name to validate
     * @return bool True if valid kebab-case
     */
    function is_valid_machine_name(string $name): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9-]*$/', $name);
    }
}

// ============================================================================
// INITIALIZATION
// ============================================================================

// Configure error handling for development
configure_error_handling();

// Verify required PHP extensions
$requiredExtensions = ['yaml', 'json'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        throw new RuntimeException("Required PHP extension not loaded: {$ext}");
    }
}
