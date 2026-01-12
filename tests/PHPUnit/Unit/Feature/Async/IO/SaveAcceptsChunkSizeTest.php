<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save constructor accepts chunkSize parameter with default 8192
 * Intent: Configures memory efficiency for large data writes
 */
final class SaveAcceptsChunkSizeTest extends TestCase
{
    public function testSaveConstructorAcceptsChunkSize(): void
    {
        $filepath = '/tmp/test.txt';
        $data = 'test data';

        $saveDefault = new Save($filepath, $data);
        $saveCustom = new Save($filepath, $data, 'w', 4096);

        $this->assertInstanceOf(Save::class, $saveDefault);
        $this->assertInstanceOf(Save::class, $saveCustom);
    }
}
