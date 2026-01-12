<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save yields after each write chunk
 * Intent: Enables cooperative multitasking during large writes
 */
final class SaveYieldsPerChunkTest extends TestCase
{
    public function testSaveYieldsAfterEachChunk(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $chunkSize = 10;
        $data = str_repeat('a', 35); // 35 bytes = 4 chunks (10+10+10+5)

        $save = new Save($tempFile, $data, 'w', $chunkSize);
        $generator = $save();

        $yieldCount = 0;
        while ($generator->valid()) {
            $yieldCount++;
            $generator->next();
        }

        // Should yield once per chunk (4 chunks)
        $this->assertSame(4, $yieldCount);

        unlink($tempFile);
    }
}
