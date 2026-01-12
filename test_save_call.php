<?php

require_once 'vendor/autoload.php';

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Save;

$tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
$data = 'test data with known length';

$generator = (function () use ($tempFile, $data) {
    $buffer = [];
    $bytesWritten = yield Call::call(new Save($tempFile, $data), $buffer);
    echo 'Inside generator, bytes written: ' . var_export($bytesWritten, true) . PHP_EOL;
    return $bytesWritten;
})();

// Consume the generator
while ($generator->valid()) {
    $value = $generator->current();
    echo 'Yielded: ' . var_export($value, true) . PHP_EOL;
    $generator->next();
}

$bytesWritten = $generator->getReturn();
echo 'Final return value: ' . var_export($bytesWritten, true) . PHP_EOL;
echo 'Expected: ' . strlen($data) . PHP_EOL;
echo 'Match: ' . ($bytesWritten === strlen($data) ? 'YES' : 'NO') . PHP_EOL;

unlink($tempFile);
