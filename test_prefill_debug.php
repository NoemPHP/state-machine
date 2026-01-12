<?php

/**
 * Debug test for prefill mechanism
 *
 * Tests the interaction prefill flow with verbose output
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/machines/cli-agent/bootstrap.php';

echo "=== Prefill Mechanism Debug Test ===\n\n";

try {
    // Step 1: Load machine-agent directly
    echo "[1] Loading machine-agent...\n";
    $generator = \Noem\State\Feature\Loader\Holon::fromYaml(
        file_get_contents(__DIR__ . '/machines/machine-agent/holon.yml')
    );
    echo "    ✓ Machine-agent loaded\n\n";

    // Step 2: Create and attach interaction adapter
    echo "[2] Creating InteractionAdapter...\n";
    $adapter = new \CliAgent\InteractionAdapter($generator, verbose: true);
    $adapter->attach();
    echo "    ✓ Adapter attached\n\n";

    // Step 3: Prefill the initial description
    echo "[3] Prefilling 'request-machine-description' interaction...\n";
    $adapter->prefill('request-machine-description', 'A simple todo list manager');
    echo "    ✓ Prefilled with: 'A simple todo list manager'\n\n";

    // Step 4: Run the generator
    echo "[4] Running machine-agent...\n";
    echo "    (Should automatically use prefilled description without prompting)\n\n";

    $runtime = new \Noem\State\StandardRuntime(
        $generator,
        new \Noem\State\RuntimeConfig(maxIterations: 50)
    );
    $runtime->run();

    echo "\n[5] Execution complete!\n";

    // Check context
    $generator->dispatch((object)[
        'type' => 'check',
        'callback' => function() {
            $userRequest = $this->get('user_request', null);
            echo "\n=== Context Check ===\n";
            echo "User Request: " . ($userRequest ?? 'NOT SET') . "\n";
        }
    ]);

} catch (\Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
