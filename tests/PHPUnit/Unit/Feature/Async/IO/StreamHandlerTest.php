<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\StreamHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: StreamHandler reads resource incrementally
 */
#[Group('async'), Group('io-operations')]
class StreamHandlerTest extends TestCase
{
    public function testReadsResourceIncrementally(): void
    {
        $content = 'Hello, World!';
        $resource = fopen('data://text/plain,' . $content, 'r');

        $handler = new StreamHandler($resource);
        $generator = $handler();

        $result = '';
        $chunkCount = 0;
        foreach ($generator as $char) {
            $result .= $char;
            $chunkCount++;
        }

        $this->assertSame($content, $result, 'Should read entire content');
        $this->assertGreaterThan(0, $chunkCount, 'Should yield content incrementally');
    }

    public function testYieldsCharacterByCharacter(): void
    {
        $content = 'ABC';
        $resource = fopen('data://text/plain,' . $content, 'r');

        $handler = new StreamHandler($resource);
        $generator = $handler();

        $chars = [];
        foreach ($generator as $char) {
            $chars[] = $char;
        }

        $this->assertSame(['A', 'B', 'C'], $chars, 'Should yield one character at a time');
    }

    public function testConstructsWithResource(): void
    {
        $resource = fopen('data://text/plain,test', 'r');
        $handler = new StreamHandler($resource);

        $this->assertInstanceOf(StreamHandler::class, $handler);
    }

    public function testThrowsOnInvalidResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StreamHandler('not a resource');
    }
}
