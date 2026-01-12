<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save creates file if it does not exist (mode w)
 * Intent: Standard file creation behavior
 */
final class SaveCreatesFileTest extends TestCase
{
    public function testSaveCreatesFileIfNotExists(): void
    {
        $tempFile = sys_get_temp_dir() . '/save_test_new_' . uniqid() . '.txt';
        $this->assertFileDoesNotExist($tempFile);

        $data = 'test data';
        $save = new Save($tempFile, $data, 'w');
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $this->assertFileExists($tempFile);

        unlink($tempFile);
    }
}
