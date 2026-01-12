<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Mode 'x' creates file exclusively, fails if exists
 * Intent: Prevents accidental file overwrites with exclusive creation
 */
final class SaveModeXTest extends TestCase
{
    public function testModeXCreatesFileExclusively(): void
    {
        $tempFile = sys_get_temp_dir() . '/save_test_exclusive_' . uniqid() . '.txt';
        $this->assertFileDoesNotExist($tempFile);

        $data = 'exclusive content';
        $save = new Save($tempFile, $data, 'x');
        $generator = $save();

        while ($generator->valid()) {
            $generator->next();
        }

        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertSame('exclusive content', $content);

        unlink($tempFile);
    }
}
