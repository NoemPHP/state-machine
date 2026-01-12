<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Default mode is 'w' when not specified
 * Intent: Sensible default for common use case
 */
final class SaveDefaultModeTest extends TestCase
{
    public function testDefaultModeIsW(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        file_put_contents($tempFile, 'will be overwritten');

        $newData = 'new content';
        $save = new Save($tempFile, $newData); // No mode specified

        $generator = $save();
        while ($generator->valid()) {
            $generator->next();
        }

        // Should have overwritten (not appended)
        $content = file_get_contents($tempFile);
        $this->assertSame('new content', $content);

        unlink($tempFile);
    }
}
