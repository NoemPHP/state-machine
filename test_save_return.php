<?php

require_once 'vendor/autoload.php';

use Noem\State\Feature\Async\IO\Save;

$tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
$data = 'test data with known length';
$save = new Save($tempFile, $data);
$generator = $save();

// Consume the generator
while ($generator->valid()) {
    $generator->next();
}

$bytesWritten = $generator->getReturn();
echo 'Bytes written: ' . var_export($bytesWritten, true) . PHP_EOL;
echo 'Expected: ' . strlen($data) . PHP_EOL;
echo 'Match: ' . ($bytesWritten === strlen($data) ? 'YES' : 'NO') . PHP_EOL;

unlink($tempFile);
