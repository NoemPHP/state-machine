<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save writes string data in chunks of chunkSize bytes
 * Intent: Prevents memory exhaustion with large string data
 */
final class SaveWritesInChunksTest extends TestCase
{
    public function testSaveWritesDataInChunks(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $chunkSize = 8;
        $data = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; // 26 bytes

        $save = new Save($tempFile, $data, 'w', $chunkSize);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        // Verify complete data was written despite chunking
        $content = file_get_contents($tempFile);
        $this->assertSame($data, $content);

        unlink($tempFile);
    }
}
