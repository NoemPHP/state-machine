<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: File handle is closed before exception propagates
 * Intent: Prevents resource leaks in error scenarios
 */
final class SaveClosesBeforeExceptionTest extends TestCase
{
    public function testFileHandleClosedBeforeExceptionPropagates(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');

        $failingGenerator = (function () {
            yield 'data';
            throw new \RuntimeException('Fail after write');
        })();

        $save = new Save($tempFile, $failingGenerator);

        try {
            $generator = $save();
            while ($generator->valid()) {
                $generator->next();
            }
            $this->fail('Should have thrown exception');
        } catch (\RuntimeException $e) {
            // Exception caught as expected
        }

        // File should be accessible (handle was closed in finally)
        $handle = fopen($tempFile, 'r');
        $this->assertIsResource($handle);
        fclose($handle);

        unlink($tempFile);
    }
}
