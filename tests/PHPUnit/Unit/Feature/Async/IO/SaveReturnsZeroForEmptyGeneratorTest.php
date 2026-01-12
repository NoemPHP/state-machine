<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Returns 0 bytes when generator yields nothing
 * Intent: Handles edge case of empty generator gracefully
 */
final class SaveReturnsZeroForEmptyGeneratorTest extends TestCase
{
    public function testSaveReturnsZeroForEmptyGenerator(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');

        // Empty generator (yields nothing)
        $emptyGenerator = (function () {
            return;
            yield; // Never reached
        })();

        $save = new Save($tempFile, $emptyGenerator);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $bytesWritten = $generator->getReturn();
        $this->assertSame(0, $bytesWritten);

        unlink($tempFile);
    }
}
