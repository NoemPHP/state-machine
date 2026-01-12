<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save works with custom user generator as data source
 * Intent: Supports arbitrary generator-based data sources
 */
final class SaveCustomGeneratorTest extends TestCase
{
    public function testSaveWorksWithCustomGenerator(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');

        // Custom generator that produces computed data
        $customGenerator = (function () {
            for ($i = 1; $i <= 5; $i++) {
                yield "Line $i\n";
            }
        })();

        $save = new Save($tempFile, $customGenerator);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $content = file_get_contents($tempFile);
        $expected = "Line 1\nLine 2\nLine 3\nLine 4\nLine 5\n";
        $this->assertSame($expected, $content);

        unlink($tempFile);
    }
}
