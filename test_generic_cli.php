<?php

/**
 * Test the generic CLI agent
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== Testing Generic CLI Agent ===\n\n";

try {
    // Load CLI agent
    $cliAgent = \Noem\State\Feature\Loader\Holon::fromYaml(
        file_get_contents(__DIR__ . '/machines/cli-agent/holon.yml')
    );

    echo "[CLI Agent loaded]\n";
    echo "[Starting runtime...]\n\n";

    // Run with limited iterations for debugging
    $runtime = new \Noem\State\StandardRuntime(
        $cliAgent,
        new \Noem\State\RuntimeConfig(maxIterations: 100)
    );

    $runtime->run();

    echo "\n[Runtime complete]\n";

} catch (\Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
