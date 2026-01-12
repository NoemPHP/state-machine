<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Final chunk may be smaller than chunkSize
 * Intent: Handles data not evenly divisible by chunk size
 */
final class SaveHandlesPartialChunkTest extends TestCase
{
    public function testSaveHandlesPartialFinalChunk(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $chunkSize = 10;
        $data = str_repeat('X', 25); // 25 bytes = 2 full chunks + 1 partial (5 bytes)

        $save = new Save($tempFile, $data, 'w', $chunkSize);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        // Verify all data written including partial chunk
        $content = file_get_contents($tempFile);
        $this->assertSame($data, $content);
        $this->assertSame(25, strlen($content));

        unlink($tempFile);
    }
}
