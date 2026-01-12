<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Throws Exception when file cannot be opened
 * Intent: Clear error reporting for file access failures
 */
final class SaveThrowsOnOpenFailureTest extends TestCase
{
    public function testSaveThrowsExceptionWhenCannotOpenFile(): void
    {
        $invalidPath = '/root/restricted/cannot_write_here.txt';
        $data = 'test data';

        $save = new Save($invalidPath, $data);

        $this->expectException(\Exception::class);
        $generator = $save();

        // Consume to trigger file open
        while ($generator->valid()) {
            $generator->next();
        }
    }
}
