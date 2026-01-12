<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Bytes written matches data length for complete write
 * Intent: Confirms successful write operation completion
 */
final class SaveBytesMatchDataLengthTest extends TestCase
{
    public function testBytesWrittenMatchesDataLength(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = 'This is a test string with known length';

        $save = new Save($tempFile, $data);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $bytesWritten = $generator->getReturn();
        $this->assertSame(strlen($data), $bytesWritten);

        unlink($tempFile);
    }
}
