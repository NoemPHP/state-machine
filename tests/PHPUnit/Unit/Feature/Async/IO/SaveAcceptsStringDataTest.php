<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save constructor accepts data as string
 * Intent: Enables direct writing of string data to file
 */
final class SaveAcceptsStringDataTest extends TestCase
{
    public function testSaveConstructorAcceptsStringData(): void
    {
        $filepath = '/tmp/test.txt';
        $data = 'test string data';

        $save = new Save($filepath, $data);

        $this->assertInstanceOf(Save::class, $save);
    }
}
