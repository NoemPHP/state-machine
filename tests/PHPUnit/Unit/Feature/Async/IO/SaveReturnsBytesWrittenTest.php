<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save generator returns total bytes written as int
 * Intent: Provides metadata about write operation for verification
 */
final class SaveReturnsBytesWrittenTest extends TestCase
{
    public function testSaveReturnsIntBytesWritten(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = 'test data';

        $save = new Save($tempFile, $data);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $bytesWritten = $generator->getReturn();
        $this->assertIsInt($bytesWritten);

        unlink($tempFile);
    }
}
