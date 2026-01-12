<?php

declare(strict_types=1);

require 'vendor/autoload.php';

echo "=== Testing Machine-Agent Interaction Discovery ===\n\n";

// Load the machine-agent
$machine = \Noem\State\Feature\Loader\Holon::fromYaml('machines/machine-agent/holon.yml');

echo "✓ Machine loaded successfully!\n";
echo "  Type: " . get_class($machine) . "\n\n";

// Try to query interactions via abilities
try {
    echo "Querying interaction capabilities...\n";
    $capabilities = $machine->abilities('enumerate-interactions');
    $interactions = $capabilities['interactions'] ?? [];

    echo "✓ Interactions discovered: " . count($interactions) . "\n\n";

    foreach ($interactions as $interaction) {
        echo "  • ID: {$interaction['id']}\n";
        echo "    Type: {$interaction['type']}\n";
        echo "    State: {$interaction['state']}\n";
        echo "    Question: {$interaction['question']}\n";
        if (!empty($interaction['metadata'])) {
            echo "    Metadata: " . json_encode($interaction['metadata'], JSON_PRETTY_PRINT) . "\n";
        }
        echo "\n";
    }

    echo "=== Test Passed! ===\n";
} catch (\Exception $e) {
    echo "✗ Error querying interactions: " . $e->getMessage() . "\n";
    echo "  Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
