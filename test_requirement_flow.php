<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/machines/cli-agent/bootstrap.php';
require_once __DIR__ . '/machines/machine-agent/bootstrap.php';

echo "=== Testing Requirement Analysis Flow ===\n\n";

// Simulate answering interactions with pre-filled responses
$cliAgentPath = machines_path('cli-agent', 'holon.yml');
$machineAgentPath = machines_path('machine-agent', 'holon.yml');

echo "[Loading machine-agent directly to test analysis...]\n";

// Load machine-agent
$machineAgent = \Noem\State\Feature\Loader\Holon::fromYaml(
    file_get_contents($machineAgentPath),
    ['autoRun' => false]
);

// Create interaction adapter with prefill
$adapter = new \CliAgent\InteractionAdapter($machineAgent, verbose: true);

// Prefill responses for testing
$adapter->prefill('request-machine-description', 'a wedding planner');
$adapter->prefill('clarifying-question', '0'); // Select first option
$adapter->attach();

echo "\n[Running machine-agent with prefilled responses...]\n\n";

// Run the machine
$runtime = new \Noem\State\StandardRuntime(
    $machineAgent,
    new \Noem\State\RuntimeConfig(maxIterations: 50)
);

$runtime->run();

echo "\n[Test complete]\n";
