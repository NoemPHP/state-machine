<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save collects all yielded values from Generator
 * Intent: Accumulates data from generator for writing to file
 */
final class SaveCollectsYieldedValuesTest extends TestCase
{
    public function testSaveCollectsAllYieldedValues(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');

        $dataGenerator = (function () {
            yield 'chunk1';
            yield 'chunk2';
            yield 'chunk3';
        })();

        $save = new Save($tempFile, $dataGenerator);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $content = file_get_contents($tempFile);
        $this->assertSame('chunk1chunk2chunk3', $content);

        unlink($tempFile);
    }
}
