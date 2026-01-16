<?php
/**
 * Process Wrapper Bootstrap
 * PSR-4 autoloader for ProcessWrapper namespace
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'ProcessWrapper\\')) {
        $relativePath = str_replace('ProcessWrapper\\', '', $class);
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativePath) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
