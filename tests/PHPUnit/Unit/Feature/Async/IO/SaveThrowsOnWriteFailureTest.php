<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Throws Exception when write operation fails
 * Intent: Detects and reports write failures (permissions, disk full)
 */
final class SaveThrowsOnWriteFailureTest extends TestCase
{
    public function testSaveThrowsOnWriteFailure(): void
    {
        // Create a read-only file
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        chmod($tempFile, 0444); // Read-only

        $data = 'attempt to write';
        $save = new Save($tempFile, $data);

        $this->expectException(\Exception::class);
        $generator = $save();

        while ($generator->valid()) {
            $generator->next();
        }

        // Cleanup
        chmod($tempFile, 0644);
        unlink($tempFile);
    }
}
