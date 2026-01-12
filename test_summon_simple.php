<?php

/**
 * Test summon in simplest possible context
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== Testing Summon in Simple Context ===\n\n";

try {
    // Create a minimal test machine with summon capability
    echo "[1] Creating test machine...\n";

    $testMachine = new \Noem\State\RegionBuilder()
        ->use(new \Noem\State\Feature\Loader\RegionLoader())
        ->use(new \Noem\State\Feature\ExtendedState\ExtendedState())
        ->state('test', function($b) {
            $b->onEnter(function($t) {
                echo "[2] In test state, attempting summon...\n";

                try {
                    $subAgent = $this->summon(__DIR__ . '/machines/machine-agent/holon.yml');
                    echo "[3] Summon returned successfully!\n";
                    echo "    Type: " . get_class($subAgent) . "\n";
                } catch (\Exception $e) {
                    echo "[ERROR] Summon failed: " . $e->getMessage() . "\n";
                    echo "    File: " . $e->getFile() . ":" . $e->getLine() . "\n";
                }
            });
        })
        ->initial('test')
        ->final('test')
        ->build();

    echo "[4] Test machine built, triggering...\n";
    $testMachine->trigger((object)[]);

    echo "[5] Test complete!\n";

} catch (\Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
