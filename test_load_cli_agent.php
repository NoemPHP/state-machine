<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "=== Testing CLI Agent Load ===\n\n";

try {
    echo "[1] Loading CLI agent YAML...\n";
    $yaml = file_get_contents(__DIR__ . '/machines/cli-agent/holon.yml');
    echo "    ✓ YAML loaded\n\n";

    echo "[2] Parsing with Holon::fromYaml...\n";
    $cliAgent = \Noem\State\Feature\Loader\Holon::fromYaml($yaml);
    echo "    ✓ CLI Agent parsed\n";
    echo "    Type: " . get_class($cliAgent) . "\n\n";

    echo "[3] Test complete!\n";

} catch (\Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
}
