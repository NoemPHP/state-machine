<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save constructor accepts data as Generator
 * Intent: Enables composable data pipelines from other I/O operations
 */
final class SaveAcceptsGeneratorDataTest extends TestCase
{
    public function testSaveConstructorAcceptsGenerator(): void
    {
        $filepath = '/tmp/test.txt';
        $generator = (function () {
            yield 'chunk1';
            yield 'chunk2';
        })();

        $save = new Save($filepath, $generator);

        $this->assertInstanceOf(Save::class, $save);
    }
}
