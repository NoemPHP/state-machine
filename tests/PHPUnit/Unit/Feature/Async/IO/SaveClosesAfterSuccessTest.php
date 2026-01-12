<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: File handle is closed after successful write
 * Intent: Proper resource management in success path
 */
final class SaveClosesAfterSuccessTest extends TestCase
{
    public function testFileHandleClosedAfterSuccess(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = 'successful write';

        $save = new Save($tempFile, $data);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        // Should be able to open file immediately (handle was closed)
        $handle = fopen($tempFile, 'r');
        $this->assertIsResource($handle);
        $content = fread($handle, 1024);
        $this->assertSame($data, $content);
        fclose($handle);

        unlink($tempFile);
    }
}
