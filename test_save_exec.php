<?php

require_once 'vendor/autoload.php';

use Noem\State\Feature\Async\IO\Save;
use Noem\State\Feature\Async\IO\Exec;

$tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
$command = 'echo "Composed Exec output"';

// Create the exec generator
$execGenerator = (new Exec($command))();

echo "Exec generator type: " . get_class($execGenerator) . PHP_EOL;
echo "Exec generator valid: " . ($execGenerator->valid() ? 'YES' : 'NO') . PHP_EOL;

// Create Save with the exec generator
$save = new Save($tempFile, $execGenerator);
$saveGenerator = $save();

// Consume the save generator
$iterations = 0;
while ($saveGenerator->valid() && $iterations < 100) {
    $saveGenerator->next();
    $iterations++;
}

echo "Iterations: $iterations" . PHP_EOL;
$content = file_get_contents($tempFile);
echo "File content length: " . strlen($content) . PHP_EOL;
echo "File content: '$content'" . PHP_EOL;
echo "Expected substring present: " . (strpos($content, 'Composed Exec output') !== false ? 'YES' : 'NO') . PHP_EOL;

unlink($tempFile);
