<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Load;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Load reads file as generator
 */
#[Group('async'), Group('io-operations')]
class LoadTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        $this->testFile = sys_get_temp_dir() . '/load_test_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    public function testCreatesGenerator(): void
    {
        file_put_contents($this->testFile, 'test content');

        $load = new Load($this->testFile);
        $generator = $load();

        $this->assertInstanceOf(\Generator::class, $generator, 'Load should return a generator');
    }

    public function testReadsFileContent(): void
    {
        $content = 'Hello, World!';
        file_put_contents($this->testFile, $content);

        $load = new Load($this->testFile);
        $generator = $load();

        $result = '';
        foreach ($generator as $char) {
            $result .= $char;
        }

        $this->assertSame($content, $result, 'Should read entire file content');
    }

    public function testConstructsWithFilePath(): void
    {
        file_put_contents($this->testFile, 'test');
        $load = new Load($this->testFile);

        $this->assertInstanceOf(Load::class, $load);
    }
}
