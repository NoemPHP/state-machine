<?php

declare(strict_types=1);

namespace Tests\Noem\State\PHPUnit\Unit\Feature\Async\IO\Replace;

use Noem\State\Feature\Async\IO\Replace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Context helper 'replace' accepts file path, search string, and replacement string; streams with constant memory
 *
 * Intent: Developers can replace literal text in huge files without memory constraints
 *
 * This test validates that:
 * 1. Replace class can be instantiated with filepath, search, replacement
 * 2. Replace returns generator (streaming operation)
 * 3. Replace performs literal string replacement
 * 4. Replace handles matches spanning chunk boundaries
 * 5. Replace returns replacement count
 * 6. Replace respects limit parameter
 * 7. Replace uses constant memory O(chunk + search_length)
 */
#[Group('async'), Group('io-operations'), Group('replace')]
final class ContextHelperTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/replace_test_' . uniqid();
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->testDir . '/*') ?: []);
        rmdir($this->testDir);
    }

    public function testCreatesGenerator(): void
    {
        $testFile = $this->testDir . '/test.txt';
        file_put_contents($testFile, 'Hello world!');

        $replace = new Replace($testFile, 'Hello', 'Hi');
        $generator = $replace();

        $this->assertInstanceOf(\Generator::class, $generator, 'Replace should return a generator');
    }

    public function testPerformsLiteralReplacement(): void
    {
        $testFile = $this->testDir . '/test.txt';
        file_put_contents($testFile, 'Hello world! Hello universe!');

        $replace = new Replace($testFile, 'Hello', 'Hi');
        $generator = $replace();

        // Consume generator
        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(2, $count, 'Should replace 2 occurrences');
        $this->assertSame('Hi world! Hi universe!', file_get_contents($testFile));
    }

    public function testReturnsReplacementCount(): void
    {
        $testFile = $this->testDir . '/count.txt';
        file_put_contents($testFile, 'foo bar foo baz foo');

        $replace = new Replace($testFile, 'foo', 'qux');
        $generator = $replace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(3, $count, 'Should return count of replacements made');
    }

    public function testRespectsLimitParameter(): void
    {
        $testFile = $this->testDir . '/limit.txt';
        file_put_contents($testFile, 'foo foo foo foo');

        // Replace only first 2 occurrences
        $replace = new Replace($testFile, 'foo', 'bar', 2);
        $generator = $replace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(2, $count);
        $this->assertSame('bar bar foo foo', file_get_contents($testFile));
    }

    public function testHandlesMatchesSpanningChunkBoundaries(): void
    {
        $testFile = $this->testDir . '/boundary.txt';

        // Create content where search string spans chunk boundary
        // Default chunk size is 8192, so we create content that has a match at boundary
        $beforeMatch = str_repeat('a', 8190);
        $search = 'BOUNDARY';
        $afterMatch = str_repeat('b', 100);
        file_put_contents($testFile, $beforeMatch . $search . $afterMatch);

        $replace = new Replace($testFile, 'BOUNDARY', 'REPLACED');
        $generator = $replace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(1, $count, 'Should find and replace boundary-spanning match');

        $content = file_get_contents($testFile);
        $this->assertStringNotContainsString('BOUNDARY', $content);
        $this->assertStringContainsString('REPLACED', $content);
    }

    public function testStreamsWithConstantMemory(): void
    {
        // Create a file larger than default chunk size (8192 bytes)
        $testFile = $this->testDir . '/large.txt';
        $content = str_repeat('NEEDLE ', 5000); // ~35KB
        file_put_contents($testFile, $content);

        $memoryBefore = memory_get_usage();

        $replace = new Replace($testFile, 'NEEDLE', 'HAYSTACK');
        $generator = $replace();

        iterator_to_array($generator);

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Memory usage should be reasonable (not loading entire file into memory multiple times)
        // Allow generous overhead since we're using temp files and buffering
        $this->assertLessThan(100000, $memoryUsed, 'Memory usage should be reasonable');

        // Verify ALL content was replaced
        $resultContent = file_get_contents($testFile);
        $this->assertStringContainsString('HAYSTACK', $resultContent);
        $needleCount = substr_count($resultContent, 'NEEDLE');
        $this->assertSame(0, $needleCount, "Should have replaced all NEEDLE occurrences, but {$needleCount} remain");
    }

    public function testHandlesEmptyFile(): void
    {
        $testFile = $this->testDir . '/empty.txt';
        file_put_contents($testFile, '');

        $replace = new Replace($testFile, 'foo', 'bar');
        $generator = $replace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(0, $count);
        $this->assertSame('', file_get_contents($testFile));
    }

    public function testHandlesNoMatches(): void
    {
        $testFile = $this->testDir . '/nomatch.txt';
        file_put_contents($testFile, 'Hello world');

        $replace = new Replace($testFile, 'foo', 'bar');
        $generator = $replace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(0, $count);
        $this->assertSame('Hello world', file_get_contents($testFile));
    }

    public function testHandlesMatchExactlyAtChunkBoundary(): void
    {
        $testFile = $this->testDir . '/exact_boundary.txt';

        // Use small chunk size (16 bytes) for precise boundary testing
        // Content: "1234567890123456FIND123"
        //          ^--- chunk 1 ---^^--- chunk 2 --^
        // "FIND" starts at position 16 (exactly at chunk boundary)
        file_put_contents($testFile, '1234567890123456FIND123');

        // With chunkSize=16, first chunk is "1234567890123456", second chunk is "FIND123"
        // The match is found completely in the second chunk
        $replace = new Replace($testFile, 'FIND', 'DONE', -1, 16);
        $generator = $replace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(1, $count);
        $this->assertSame('1234567890123456DONE123', file_get_contents($testFile));
    }

    public function testHandlesMatchSplitAcrossChunks(): void
    {
        $testFile = $this->testDir . '/split_match.txt';

        // Content: "12345678901234SPLIT567"
        //          ^--- chunk 1 ---^^--- chunk 2 --^
        // With chunkSize=16: chunk1="12345678901234SP", chunk2="LIT567"
        // "SPLIT" spans positions 14-18, split as "SP" + "LIT"
        file_put_contents($testFile, '12345678901234SPLIT567');

        $replace = new Replace($testFile, 'SPLIT', 'MATCH', -1, 16);
        $generator = $replace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(1, $count, 'Should find match spanning chunk boundary');
        $this->assertSame('12345678901234MATCH567', file_get_contents($testFile));
    }

    public function testHandlesMultipleMatchesAcrossChunks(): void
    {
        $testFile = $this->testDir . '/multi_boundary.txt';

        // Create content with matches in different chunk positions
        // With chunkSize=10: tests multiple boundary scenarios
        file_put_contents($testFile, 'abFINDcdeFINDefghFINDij');

        $replace = new Replace($testFile, 'FIND', 'XX', -1, 10);
        $generator = $replace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(3, $count);
        $this->assertSame('abXXcdeXXefghXXij', file_get_contents($testFile));
    }
}
