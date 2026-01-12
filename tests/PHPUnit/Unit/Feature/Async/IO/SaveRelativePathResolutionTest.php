<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Relative paths resolve against current working directory
 * Intent: Standard filesystem path resolution semantics
 */
final class SaveRelativePathResolutionTest extends TestCase
{
    public function testRelativePathResolvesAgainstCwd(): void
    {
        $cwd = getcwd();
        $relativePath = 'save_test_cwd_' . uniqid() . '.txt';
        $expectedAbsolutePath = $cwd . '/' . $relativePath;

        $data = 'cwd test';
        $save = new Save($relativePath, $data);
        $generator = $save();

        while ($generator->valid()) {
            $generator->next();
        }

        // Verify file exists at expected absolute path
        $this->assertFileExists($expectedAbsolutePath);
        $content = file_get_contents($expectedAbsolutePath);
        $this->assertSame($data, $content);

        unlink($expectedAbsolutePath);
    }
}
