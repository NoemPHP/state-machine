<?php

declare(strict_types=1);

require 'vendor/autoload.php';

echo "=== Debugging Interaction Registration ===\n\n";

// First, let's see what the raw YAML contains
echo "1. Reading raw YAML file...\n";
$yamlContent = file_get_contents('machines/machine-agent/holon.yml');
$rawYaml = yaml_parse($yamlContent);

echo "   Top-level keys: " . implode(', ', array_keys($rawYaml)) . "\n";

if (isset($rawYaml['interactions'])) {
    echo "   ✓ 'interactions' key found in YAML\n";
    echo "   Number of interactions: " . count($rawYaml['interactions']) . "\n";
    foreach ($rawYaml['interactions'] as $interaction) {
        echo "     - {$interaction['id']} ({$interaction['type']})\n";
    }
} else {
    echo "   ✗ 'interactions' key NOT found in YAML\n";
}
echo "\n";

// Now let's load via Holon and see what happens
echo "2. Loading machine via Holon (without auto-run)...\n";

try {
    // Load the YAML content and modify it to disable auto-run
    $yamlContent = file_get_contents('machines/machine-agent/holon.yml');
    $yamlData = yaml_parse($yamlContent);

    // Disable autoRun to prevent the machine from starting
    $yamlData['machine']['eventLoop']['autoRun'] = false;

    // Convert back to YAML
    $modifiedYaml = yaml_emit($yamlData);

    $holon = \Noem\State\Feature\Loader\Holon::fromYaml($modifiedYaml);

    echo "   ✓ Machine loaded successfully\n";
    echo "   Type: " . get_class($holon) . "\n\n";

    // Try to access the InteractionRegistry from ChainMail
    echo "3. Checking InteractionRegistry in ChainMail...\n";

    $registry = $holon->chainMail->get(\Noem\State\Feature\Interaction\InteractionRegistry::class);

    if ($registry === null) {
        echo "   ✗ InteractionRegistry NOT found in ChainMail\n";
        echo "   Available services:\n";

        // Try to introspect ChainMail
        $reflection = new ReflectionClass($holon->chainMail);
        $meshProp = $reflection->getProperty('mesh');
        $meshProp->setAccessible(true);
        $mesh = $meshProp->getValue($holon->chainMail);

        foreach ($mesh as $key => $value) {
            if (is_object($value)) {
                echo "     - " . get_class($value) . "\n";
            } else {
                echo "     - " . gettype($value) . "\n";
            }
        }
    } else {
        echo "   ✓ InteractionRegistry found\n";

        // Check what's registered
        $all = $registry->all();
        echo "   Registered interactions: " . count($all) . "\n";

        if (count($all) > 0) {
            foreach ($all as $def) {
                echo "     - {$def->id} ({$def->type}) in state '{$def->state}'\n";
            }
        } else {
            echo "     (none registered)\n";
        }
    }

} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";
