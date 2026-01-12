<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save::__invoke() returns Generator
 * Intent: Provides non-blocking write operation compatible with async scheduler
 */
final class SaveReturnsGeneratorTest extends TestCase
{
    public function testSaveInvokeReturnsGenerator(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = 'test data';

        $save = new Save($tempFile, $data);
        $result = $save();

        $this->assertInstanceOf(\Generator::class, $result);

        unlink($tempFile);
    }
}
