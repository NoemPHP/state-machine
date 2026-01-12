<?php

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use Noem\State\StandardRuntime;

require __DIR__.'/vendor/autoload.php';


// Check if script is run from command line
if (!isset($argv[1])) {
    echo "Usage: php run.php <path_to_yaml_file>\n";
    exit(1);
}

$yamlPath = $argv[1];

// Verify file exists
if (!is_readable($yamlPath)) {
    echo "Error: YAML file '$yamlPath' not found or unreadable\n";
    exit(2);
}

try {
    // Create region from YAML file using Holon
    // fromYaml() automatically detects and reads file paths
    // Note: If the machine has eventLoop.autoRun: true, this will execute and return the final result
    // Otherwise, it returns a Region that we need to execute manually
    $result = Holon::fromYaml($yamlPath);

    // Check if result is a Region (manual execution needed) or already executed (autoRun)
    if ($result instanceof Region) {
        // Execute using standard runtime
        $runtime = new StandardRuntime($result);
        $isStillRunning = $runtime->run();

        // run() returns false when complete (blocking mode with steps=0)
        echo "Execution complete: " . ($isStillRunning ? 'false' : 'true') . "\n";
    } else {
        // Machine auto-executed, result is the final trigger object
        echo "Execution complete (auto-run)\n";
    }
} catch (\Throwable $e) {
    echo "Error during execution:\n" . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(3);
}
