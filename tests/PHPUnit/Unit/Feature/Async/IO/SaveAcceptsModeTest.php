<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save constructor accepts mode parameter with default 'w'
 * Intent: Configures write behavior (overwrite, append, exclusive)
 */
final class SaveAcceptsModeTest extends TestCase
{
    public function testSaveConstructorAcceptsModeParameter(): void
    {
        $filepath = '/tmp/test.txt';
        $data = 'test data';

        $saveW = new Save($filepath, $data, 'w');
        $saveA = new Save($filepath, $data, 'a');
        $saveX = new Save($filepath, $data, 'x');

        $this->assertInstanceOf(Save::class, $saveW);
        $this->assertInstanceOf(Save::class, $saveA);
        $this->assertInstanceOf(Save::class, $saveX);
    }
}
