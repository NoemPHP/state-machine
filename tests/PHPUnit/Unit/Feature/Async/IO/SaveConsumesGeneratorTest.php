<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save consumes Generator by calling next() until completion
 * Intent: Implements generator consumption protocol for data pipelines
 */
final class SaveConsumesGeneratorTest extends TestCase
{
    public function testSaveConsumesGeneratorUntilCompletion(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $consumed = [];

        $dataGenerator = (function () use (&$consumed) {
            $consumed[] = 'step1';
            yield 'chunk1';
            $consumed[] = 'step2';
            yield 'chunk2';
            $consumed[] = 'step3';
            yield 'chunk3';
        })();

        $save = new Save($tempFile, $dataGenerator);
        $generator = $save();

        // Consume the Save generator
        while ($generator->valid()) {
            $generator->next();
        }

        // Verify all steps were consumed
        $this->assertSame(['step1', 'step2', 'step3'], $consumed);

        unlink($tempFile);
    }
}
