<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Mode 'x' with existing file throws Exception
 * Intent: Enforces exclusive creation semantics
 */
final class SaveModeXExistingThrowsTest extends TestCase
{
    public function testModeXThrowsWhenFileExists(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $this->assertFileExists($tempFile);

        $data = 'should fail';
        $save = new Save($tempFile, $data, 'x');

        $this->expectException(\Exception::class);

        $generator = $save();
        while ($generator->valid()) {
            $generator->next();
        }

        unlink($tempFile);
    }
}
