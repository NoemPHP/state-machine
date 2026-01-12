<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save overwrites existing file when mode is 'w'
 * Intent: Implements standard overwrite semantics
 */
final class SaveOverwritesWithModeWTest extends TestCase
{
    public function testSaveOverwritesExistingFileWithModeW(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        file_put_contents($tempFile, 'original content');

        $newData = 'new content';
        $save = new Save($tempFile, $newData, 'w');
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $content = file_get_contents($tempFile);
        $this->assertSame($newData, $content);
        $this->assertStringNotContainsString('original content', $content);

        unlink($tempFile);
    }
}
