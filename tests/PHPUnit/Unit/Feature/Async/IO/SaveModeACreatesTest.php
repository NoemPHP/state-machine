<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Mode 'a' creates file if it does not exist
 * Intent: Convenient file creation with append mode
 */
final class SaveModeACreatesTest extends TestCase
{
    public function testModeACreatesFileIfNotExists(): void
    {
        $tempFile = sys_get_temp_dir() . '/save_test_append_' . uniqid() . '.txt';
        $this->assertFileDoesNotExist($tempFile);

        $data = 'new file content';
        $save = new Save($tempFile, $data, 'a');
        $generator = $save();

        while ($generator->valid()) {
            $generator->next();
        }

        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertSame('new file content', $content);

        unlink($tempFile);
    }
}
