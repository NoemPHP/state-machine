<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Returns 0 bytes for empty string data
 * Intent: Handles edge case of empty data gracefully
 */
final class SaveReturnsZeroForEmptyTest extends TestCase
{
    public function testSaveReturnsZeroForEmptyString(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = '';

        $save = new Save($tempFile, $data);
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
