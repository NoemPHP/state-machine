<?php

/**
 * Test script for prefill mechanism
 *
 * This simulates what happens when:
 * 1. CLI agent collects user input
 * 2. Summons machine-agent
 * 3. Prefills the interaction
 * 4. Machine-agent receives prefilled response automatically
 */

require_once __DIR__ . '/vendor/autoload.php';

// Simulate CLI agent flow
echo "=== Testing Prefill Mechanism ===\n\n";

// Step 1: Load CLI agent
echo "[1] Loading CLI agent...\n";
$cliAgent = \Noem\State\Feature\Loader\Holon::bootstrap(
    __DIR__ . '/machines/cli-agent/holon.yml'
);
echo "    ✓ CLI agent loaded\n\n";

// Step 2: Simulate user input by setting context
echo "[2] Simulating user input: 'A simple todo list manager'\n";
$cliAgent->dispatch((object)[
    'type' => 'set_description',
    'callback' => function() {
        $this->set('user_description', 'A simple todo list manager');
    }
]);
echo "    ✓ User description stored in CLI agent context\n\n";

// Step 3: Trigger transition to invoking_generator state
echo "[3] Transitioning to invoking_generator state...\n";
$cliAgent->dispatch((object)['type' => 'start_generation']);

// Step 4: Run the CLI agent
echo "[4] Running CLI agent (will summon machine-agent with prefill)...\n\n";
$runtime = new \Noem\State\StandardRuntime($cliAgent, maxIterations: 100);

try {
    $runtime->run();
    echo "\n[5] Execution complete!\n";

    // Check results
    $cliAgent->dispatch((object)[
        'type' => 'check_results',
        'callback' => function() {
            $success = $this->get('generation_success', false);
            $machineName = $this->get('machine_name', 'unknown');

            echo "\n=== Results ===\n";
            echo "Generation Success: " . ($success ? 'YES' : 'NO') . "\n";
            echo "Machine Name: $machineName\n";
        }
    ]);

} catch (\Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
