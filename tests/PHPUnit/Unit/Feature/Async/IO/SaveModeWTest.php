<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Mode 'w' creates new file or truncates existing
 * Intent: Standard overwrite semantics
 */
final class SaveModeWTest extends TestCase
{
    public function testModeWTruncatesExistingFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        file_put_contents($tempFile, 'original content that will be lost');

        $newData = 'new';
        $save = new Save($tempFile, $newData, 'w');
        $generator = $save();

        while ($generator->valid()) {
            $generator->next();
        }

        $content = file_get_contents($tempFile);
        $this->assertSame('new', $content);

        unlink($tempFile);
    }
}
