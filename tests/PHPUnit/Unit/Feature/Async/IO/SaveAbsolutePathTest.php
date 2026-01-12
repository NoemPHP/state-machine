<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save accepts absolute file paths
 * Intent: Explicit file location specification
 */
final class SaveAbsolutePathTest extends TestCase
{
    public function testSaveAcceptsAbsolutePath(): void
    {
        $absolutePath = sys_get_temp_dir() . '/save_test_absolute_' . uniqid() . '.txt';
        $data = 'absolute path test';

        $save = new Save($absolutePath, $data);
        $generator = $save();

        while ($generator->valid()) {
            $generator->next();
        }

        $this->assertFileExists($absolutePath);
        $content = file_get_contents($absolutePath);
        $this->assertSame($data, $content);

        unlink($absolutePath);
    }
}
