<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Number of yields matches chunks written for string data
 * Intent: Predictable yielding behavior for testing and scheduling
 */
final class SaveYieldCountTest extends TestCase
{
    public function testYieldCountMatchesChunkCount(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $chunkSize = 5;
        $data = '12345678901234567890'; // 20 bytes = 4 chunks of 5

        $save = new Save($tempFile, $data, 'w', $chunkSize);
        $generator = $save();

        $yieldCount = 0;
        while ($generator->valid()) {
            $yieldCount++;
            $generator->next();
        }

        $expectedChunks = 4; // 20 / 5 = 4 chunks
        $this->assertSame($expectedChunks, $yieldCount);

        unlink($tempFile);
    }
}
