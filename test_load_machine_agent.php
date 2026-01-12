<?php

/**
 * Test loading machine-agent directly
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== Testing Machine Agent Load ===\n\n";

try {
    echo "[1] Loading machine-agent YAML...\n";
    $yaml = file_get_contents(__DIR__ . '/machines/machine-agent/holon.yml');
    echo "    ✓ YAML loaded (" . strlen($yaml) . " bytes)\n\n";

    echo "[2] Parsing with Holon::fromYaml...\n";
    $machineAgent = \Noem\State\Feature\Loader\Holon::fromYaml($yaml);
    echo "    ✓ Machine parsed\n";
    echo "    Type: " . get_class($machineAgent) . "\n\n";

    echo "[3] Test complete - machine loads successfully!\n";

} catch (\Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
