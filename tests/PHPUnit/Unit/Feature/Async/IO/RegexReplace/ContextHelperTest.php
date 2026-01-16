<?php

declare(strict_types=1);

namespace Tests\Noem\State\PHPUnit\Unit\Feature\Async\IO\RegexReplace;

use Noem\State\Feature\Async\IO\RegexReplace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Context helper 'regexReplace' accepts file path, regex pattern, and replacement string with capture group support
 *
 * Intent: Developers can perform pattern-based file transformations using regex backreferences
 *
 * This test validates that:
 * 1. RegexReplace class can be instantiated with filepath, pattern, replacement
 * 2. RegexReplace returns generator (yields for cooperative multitasking)
 * 3. RegexReplace performs regex replacement
 * 4. RegexReplace supports capture groups and backreferences
 * 5. RegexReplace reports preg errors with preg_last_error_msg()
 * 6. RegexReplace returns replacement count
 * 7. RegexReplace respects limit parameter
 */
#[Group('async'), Group('io-operations'), Group('regex-replace')]
final class ContextHelperTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/regex_replace_test_' . uniqid();
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

        $regexReplace = new RegexReplace($testFile, '/world/', 'universe');
        $generator = $regexReplace();

        $this->assertInstanceOf(\Generator::class, $generator, 'RegexReplace should return a generator');
    }

    public function testPerformsRegexReplacement(): void
    {
        $testFile = $this->testDir . '/test.txt';
        file_put_contents($testFile, 'Hello world! Goodbye world!');

        $regexReplace = new RegexReplace($testFile, '/world/', 'universe');
        $generator = $regexReplace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(2, $count, 'Should replace 2 occurrences');
        $this->assertSame('Hello universe! Goodbye universe!', file_get_contents($testFile));
    }

    public function testSupportsCaptureGroupsAndBackreferences(): void
    {
        $testFile = $this->testDir . '/capture.txt';
        file_put_contents($testFile, 'Name: John Doe, Age: 30');

        $regexReplace = new RegexReplace($testFile, '/(\w+) (\w+)/', '$2, $1');
        $generator = $regexReplace();

        iterator_to_array($generator);

        $this->assertSame('Name: Doe, John, Age: 30', file_get_contents($testFile));
    }

    public function testReturnsReplacementCount(): void
    {
        $testFile = $this->testDir . '/count.txt';
        file_put_contents($testFile, 'foo123 bar456 baz789');

        $regexReplace = new RegexReplace($testFile, '/\d+/', 'XXX');
        $generator = $regexReplace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(3, $count, 'Should return count of replacements made');
        $this->assertSame('fooXXX barXXX bazXXX', file_get_contents($testFile));
    }

    public function testRespectsLimitParameter(): void
    {
        $testFile = $this->testDir . '/limit.txt';
        file_put_contents($testFile, 'a1 b2 c3 d4');

        // Replace only first 2 occurrences
        $regexReplace = new RegexReplace($testFile, '/\d/', 'X', 2);
        $generator = $regexReplace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(2, $count);
        $this->assertSame('aX bX c3 d4', file_get_contents($testFile));
    }

    public function testThrowsExceptionOnInvalidRegex(): void
    {
        $testFile = $this->testDir . '/invalid.txt';
        file_put_contents($testFile, 'test content');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/preg_replace|regex|pattern/i');

        // Invalid regex pattern (missing closing delimiter)
        $regexReplace = new RegexReplace($testFile, '/invalid', 'replacement');
        $generator = $regexReplace();

        // Consume generator to trigger the error
        iterator_to_array($generator);
    }

    public function testYieldsForCooperativeMultitasking(): void
    {
        $testFile = $this->testDir . '/yield.txt';
        file_put_contents($testFile, 'test content for yielding');

        $regexReplace = new RegexReplace($testFile, '/test/', 'modified');
        $generator = $regexReplace();

        $yieldCount = 0;
        foreach ($generator as $ignored) {
            $yieldCount++;
        }

        // Should yield at least once (read, replace, write operations)
        $this->assertGreaterThan(0, $yieldCount, 'Should yield for cooperative multitasking');
    }

    public function testHandlesComplexRegexPatterns(): void
    {
        $testFile = $this->testDir . '/complex.txt';
        file_put_contents($testFile, 'Email: user@example.com and admin@test.org');

        // Match email pattern and replace domain
        $regexReplace = new RegexReplace(
            $testFile,
            '/([a-z]+)@([a-z]+\.[a-z]+)/',
            '$1@replaced.com'
        );
        $generator = $regexReplace();

        iterator_to_array($generator);

        $this->assertSame('Email: user@replaced.com and admin@replaced.com', file_get_contents($testFile));
    }

    public function testHandlesEmptyFile(): void
    {
        $testFile = $this->testDir . '/empty.txt';
        file_put_contents($testFile, '');

        $regexReplace = new RegexReplace($testFile, '/foo/', 'bar');
        $generator = $regexReplace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(0, $count);
        $this->assertSame('', file_get_contents($testFile));
    }

    public function testHandlesNoMatches(): void
    {
        $testFile = $this->testDir . '/nomatch.txt';
        file_put_contents($testFile, 'Hello world');

        $regexReplace = new RegexReplace($testFile, '/foo/', 'bar');
        $generator = $regexReplace();

        iterator_to_array($generator);
        $count = $generator->getReturn();

        $this->assertSame(0, $count);
        $this->assertSame('Hello world', file_get_contents($testFile));
    }
}
