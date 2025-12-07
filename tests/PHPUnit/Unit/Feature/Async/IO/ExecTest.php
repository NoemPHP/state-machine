<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Exec;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Exec executes shell command as generator
 */
#[Group('async'), Group('io-operations')]
class ExecTest extends TestCase
{
    public function testCreatesGenerator(): void
    {
        $exec = new Exec('echo "test"');
        $generator = $exec();

        $this->assertInstanceOf(\Generator::class, $generator, 'Exec should return a generator');
    }

    public function testExecutesSimpleCommand(): void
    {
        $exec = new Exec('echo "hello"');
        $generator = $exec();

        $output = '';
        foreach ($generator as $chunk) {
            $output .= $chunk;
        }

        $this->assertStringContainsString('hello', $output, 'Should execute echo command');
    }

    public function testConstructsWithCommand(): void
    {
        $exec = new Exec('pwd');
        $this->assertInstanceOf(Exec::class, $exec);
    }

    public function testConstructsWithTimeout(): void
    {
        $exec = new Exec('sleep 1', 5);
        $this->assertInstanceOf(Exec::class, $exec);
    }
}
