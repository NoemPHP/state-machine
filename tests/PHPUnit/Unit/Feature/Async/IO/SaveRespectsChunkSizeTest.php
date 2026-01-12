<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save respects custom chunkSize parameter
 * Intent: Allows tuning memory vs I/O tradeoff
 */
final class SaveRespectsChunkSizeTest extends TestCase
{
    public function testSaveRespectsCustomChunkSize(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $chunkSize = 3;
        $data = '123456789'; // 9 bytes = 3 chunks of 3

        $save = new Save($tempFile, $data, 'w', $chunkSize);
        $generator = $save();

        $yieldCount = 0;
        while ($generator->valid()) {
            $yieldCount++;
            $generator->next();
        }

        // Should have exactly 3 chunks
        $this->assertSame(3, $yieldCount);

        unlink($tempFile);
    }
}
