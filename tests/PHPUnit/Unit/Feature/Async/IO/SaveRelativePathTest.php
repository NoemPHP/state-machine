<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save accepts relative file paths
 * Intent: Convenient path specification relative to working directory
 */
final class SaveRelativePathTest extends TestCase
{
    public function testSaveAcceptsRelativePath(): void
    {
        $relativePath = 'save_test_relative_' . uniqid() . '.txt';
        $data = 'relative path test';

        $save = new Save($relativePath, $data);
        $generator = $save();

        while ($generator->valid()) {
            $generator->next();
        }

        $this->assertFileExists($relativePath);
        $content = file_get_contents($relativePath);
        $this->assertSame($data, $content);

        unlink($relativePath);
    }
}
