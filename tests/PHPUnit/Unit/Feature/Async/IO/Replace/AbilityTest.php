<?php

declare(strict_types=1);

namespace Tests\Noem\State\PHPUnit\Unit\Feature\Async\IO\Replace;

use Noem\State\Feature\Async\IO\Replace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Ability 'replace' accepts file path, search string, and replacement string for agent invocation
 *
 * Intent: AI agents can modify files via literal string replacement tool
 *
 * This test validates that the Replace class can be used as an ability handler
 * accepting the parameters an AI agent would provide:
 * 1. file path (string)
 * 2. search string (string)
 * 3. replacement string (string)
 */
#[Group('async'), Group('io-operations'), Group('replace'), Group('abilities')]
final class AbilityTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/replace_ability_test_' . uniqid();
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->testDir . '/*') ?: []);
        rmdir($this->testDir);
    }

    public function testAcceptsFilePathSearchAndReplacementParameters(): void
    {
        // Simulate ability invocation with parameters an AI agent would provide
        $filePath = $this->testDir . '/test.txt';
        $searchString = 'old_value';
        $replacementString = 'new_value';

        file_put_contents($filePath, 'This contains old_value that should be replaced.');

        // Create Replace instance with ability parameters
        $replace = new Replace($filePath, $searchString, $replacementString);

        // Verify it can be invoked (ability handler contract)
        $generator = $replace();
        $this->assertInstanceOf(\Generator::class, $generator);

        // Execute the replacement
        iterator_to_array($generator);
        $count = $generator->getReturn();

        // Verify the ability executed correctly
        $this->assertSame(1, $count, 'Should return replacement count');
        $this->assertSame(
            'This contains new_value that should be replaced.',
            file_get_contents($filePath),
            'File content should be modified'
        );
    }

    public function testAbilityParameterTypesMatchExpectedSchema(): void
    {
        // Verify the constructor accepts the exact types an ability schema would define
        $filePath = $this->testDir . '/schema_test.txt';
        file_put_contents($filePath, 'content');

        // All parameters are strings as specified in the ability schema
        $replace = new Replace(
            $filePath,      // file_path: string
            'search',       // search: string
            'replacement'   // replacement: string
        );

        $this->assertInstanceOf(Replace::class, $replace);
    }

    public function testAbilityCanBeInvokedAsCallable(): void
    {
        $filePath = $this->testDir . '/callable_test.txt';
        file_put_contents($filePath, 'test content');

        $replace = new Replace($filePath, 'test', 'modified');

        // Verify Replace is invokable (required for ability handler)
        $this->assertTrue(is_callable($replace), 'Replace should be callable for ability invocation');

        // Invoke and verify execution
        $generator = $replace();
        iterator_to_array($generator);

        $this->assertSame('modified content', file_get_contents($filePath));
    }
}
