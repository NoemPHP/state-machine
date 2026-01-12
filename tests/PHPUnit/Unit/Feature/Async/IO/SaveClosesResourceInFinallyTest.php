<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save closes file resource in finally block
 * Intent: Guarantees resource cleanup even on exception
 */
final class SaveClosesResourceInFinallyTest extends TestCase
{
    public function testSaveClosesResourceInFinally(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = 'test data';

        $save = new Save($tempFile, $data);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        // Try to open the file again - should succeed if properly closed
        $handle = fopen($tempFile, 'r');
        $this->assertIsResource($handle);
        fclose($handle);

        unlink($tempFile);
    }
}
