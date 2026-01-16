<?php

declare(strict_types=1);

namespace Tests\Noem\State\PHPUnit\Unit\Feature\Async\IO\RegexReplace;

use Noem\State\Feature\Async\IO\RegexReplace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Ability 'regex-replace' accepts file path, regex pattern, and replacement string with capture support
 *
 * Intent: AI agents can perform pattern-based file transformations via tool
 *
 * This test validates that the RegexReplace class can be used as an ability handler
 * accepting the parameters an AI agent would provide:
 * 1. file path (string)
 * 2. regex pattern (string)
 * 3. replacement string with capture group support (string)
 */
#[Group('async'), Group('io-operations'), Group('regex-replace'), Group('abilities')]
final class AbilityTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/regex_replace_ability_test_' . uniqid();
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->testDir . '/*') ?: []);
        rmdir($this->testDir);
    }

    public function testAcceptsFilePathPatternAndReplacementParameters(): void
    {
        // Simulate ability invocation with parameters an AI agent would provide
        $filePath = $this->testDir . '/test.txt';
        $pattern = '/old_(\w+)/';
        $replacement = 'new_$1';

        file_put_contents($filePath, 'This contains old_value that should be replaced.');

        // Create RegexReplace instance with ability parameters
        $regexReplace = new RegexReplace($filePath, $pattern, $replacement);

        // Verify it can be invoked (ability handler contract)
        $generator = $regexReplace();
        $this->assertInstanceOf(\Generator::class, $generator);

        // Execute the replacement
        iterator_to_array($generator);
        $count = $generator->getReturn();

        // Verify the ability executed correctly
        $this->assertSame(1, $count, 'Should return replacement count');
        $this->assertSame(
            'This contains new_value that should be replaced.',
            file_get_contents($filePath),
            'File content should be modified with capture group'
        );
    }

    public function testAbilityParameterTypesMatchExpectedSchema(): void
    {
        // Verify the constructor accepts the exact types an ability schema would define
        $filePath = $this->testDir . '/schema_test.txt';
        file_put_contents($filePath, 'content');

        // All parameters are strings as specified in the ability schema
        $regexReplace = new RegexReplace(
            $filePath,      // file_path: string
            '/pattern/',    // pattern: string (regex)
            'replacement'   // replacement: string
        );

        $this->assertInstanceOf(RegexReplace::class, $regexReplace);
    }

    public function testAbilityCanBeInvokedAsCallable(): void
    {
        $filePath = $this->testDir . '/callable_test.txt';
        file_put_contents($filePath, 'test content');

        $regexReplace = new RegexReplace($filePath, '/test/', 'modified');

        // Verify RegexReplace is invokable (required for ability handler)
        $this->assertTrue(is_callable($regexReplace), 'RegexReplace should be callable for ability invocation');

        // Invoke and verify execution
        $generator = $regexReplace();
        iterator_to_array($generator);

        $this->assertSame('modified content', file_get_contents($filePath));
    }

    public function testSupportsCaptureGroupsInReplacement(): void
    {
        $filePath = $this->testDir . '/capture_test.txt';
        file_put_contents($filePath, 'user@example.com');

        // Pattern with capture groups, replacement using backreferences
        $regexReplace = new RegexReplace(
            $filePath,
            '/(\w+)@(\w+\.\w+)/',
            '$1@replaced.com'
        );

        $generator = $regexReplace();
        iterator_to_array($generator);

        $this->assertSame('user@replaced.com', file_get_contents($filePath));
    }
}
