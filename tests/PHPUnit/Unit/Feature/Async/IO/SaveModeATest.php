<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Mode 'a' appends to existing file without truncation
 * Intent: Enables append-only writes for logs and incremental data
 */
final class SaveModeATest extends TestCase
{
    public function testModeAAppendsToExistingFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        file_put_contents($tempFile, 'existing content');

        $appendData = ' appended';
        $save = new Save($tempFile, $appendData, 'a');
        $generator = $save();

        while ($generator->valid()) {
            $generator->next();
        }

        $content = file_get_contents($tempFile);
        $this->assertSame('existing content appended', $content);

        unlink($tempFile);
    }
}
