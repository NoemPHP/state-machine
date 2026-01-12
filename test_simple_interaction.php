<?php

declare(strict_types=1);

require 'vendor/autoload.php';

echo "=== Testing Simple Interaction Registration ===\n\n";

// Create a minimal machine with interactions programmatically
$builder = new \Noem\State\RegionBuilder();

// Add features
$builder
    ->enableFeatures(
        new \Noem\State\Feature\ExtendedState\ExtendedState(),
        new \Noem\State\Feature\Abilities\AbilitiesFeature(),
        new \Noem\State\Feature\Interaction\InteractionFeature(),
        new \Noem\State\Feature\Interaction\InteractionRegistryFeature()
    );

// Define a simple state
$builder->state('test')->onEnter(function() {
    echo "In test state!\n";

    // Try to use interact
    try {
        $result = yield from $this->interact('test-prompt');
        echo "Got result: $result\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
});

// Build the machine
$machine = $builder->build(['initial' => 'test']);

echo "✓ Machine built successfully!\n\n";

// Try to register an interaction programmatically
$registry = $machine->chainMail->get(\Noem\State\Feature\Interaction\InteractionRegistry::class);

if ($registry) {
    echo "✓ InteractionRegistry found in ChainMail\n";

    $definition = new \Noem\State\Feature\Interaction\InteractionDefinition(
        id: 'test-prompt',
        type: 'prompt',
        state: 'test',
        question: 'Test question?',
        metadata: ['placeholder' => 'Enter test']
    );

    $registry->register($definition);
    echo "✓ Interaction registered\n";

    // Verify it was registered
    $retrieved = $registry->get('test-prompt');
    if ($retrieved) {
        echo "✓ Interaction retrieved: {$retrieved->id}\n";
    }

    // Try abilities
    try {
        $capabilities = $machine->abilities('enumerate-interactions');
        echo "✓ enumerate-interactions ability works\n";
        echo "  Found " . count($capabilities['interactions']) . " interaction(s)\n";
    } catch (\Exception $e) {
        echo "✗ enumerate-interactions ability failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ InteractionRegistry NOT found in ChainMail\n";
}

echo "\n=== Test Complete ===\n";
