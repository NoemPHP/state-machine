<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Propagates exceptions from data source generator
 * Intent: Maintains exception semantics for generator pipelines
 */
final class SavePropagatesGeneratorExceptionTest extends TestCase
{
    public function testSavePropagatesGeneratorException(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');

        $failingGenerator = (function () {
            yield 'chunk1';
            throw new \RuntimeException('Generator failed');
        })();

        $save = new Save($tempFile, $failingGenerator);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Generator failed');

        $generator = $save();
        while ($generator->valid()) {
            $generator->next();
        }

        unlink($tempFile);
    }
}
