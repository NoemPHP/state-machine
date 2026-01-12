<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save constructor accepts filepath as string
 * Intent: Provides file destination for write operation
 */
final class SaveAcceptsFilepathTest extends TestCase
{
    public function testSaveConstructorAcceptsFilepath(): void
    {
        $filepath = '/tmp/test.txt';
        $data = 'test data';

        $save = new Save($filepath, $data);

        $this->assertInstanceOf(Save::class, $save);
    }
}
