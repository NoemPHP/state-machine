<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh supports memoization for get operations
 */
#[Group('middleware')]
#[Group('mesh')]
class MemoizationTest extends TestCase
{
    public function testMemoizationCachesGetOperations(): void
    {
        $callCount = 0;
        $data = [];

        $mesh = new Mesh(
            data: $data,
            offsetGet: function ($offset) use (&$callCount) {
                $callCount++;
                return "computed-{$offset}";
            }
        );

        $mesh->memoize();

        // First call
        $result1 = $mesh['key'];
        $this->assertSame(1, $callCount);

        // Second call should use cache
        $result2 = $mesh['key'];
        $this->assertSame(1, $callCount, 'Should not call offsetGet again');

        $this->assertSame($result1, $result2);
    }

    public function testMemoizationPerKey(): void
    {
        $callCount = [];
        $data = [];

        $mesh = new Mesh(
            data: $data,
            offsetGet: function ($offset) use (&$callCount) {
                $callCount[$offset] = ($callCount[$offset] ?? 0) + 1;
                return "value-{$offset}";
            }
        );

        $mesh->memoize();

        // Access key1 three times in a row
        $mesh['key1'];
        $mesh['key1'];
        $mesh['key1'];

        // The first call executes, subsequent calls use the cached result
        $this->assertSame(1, $callCount['key1']);

        // Access key2 (invalidates cache for key1)
        $mesh['key2'];

        // Access key1 again (cache was invalidated, so it calls again)
        $mesh['key2'];

        // key1 was called once initially
        $this->assertSame(1, $callCount['key1']);
        // key2 was called once (first access), second access used cache
        $this->assertSame(1, $callCount['key2']);
    }

    public function testMemoizationWithExpensiveComputation(): void
    {
        $computationCount = 0;
        $data = [];

        $mesh = new Mesh(
            data: $data,
            offsetGet: function ($offset) use (&$computationCount) {
                $computationCount++;
                // Simulate expensive computation
                return md5($offset);
            }
        );

        $mesh->memoize();

        $result1 = $mesh['test'];
        $result2 = $mesh['test'];
        $result3 = $mesh['test'];

        $this->assertSame(1, $computationCount);
        $this->assertSame($result1, $result2);
        $this->assertSame($result2, $result3);
    }

    public function testMemoizationCachesUntilDataChanges(): void
    {
        $data = ['key' => 'value1'];
        $mesh = new Mesh(data: $data);
        $mesh->memoize();

        $result1 = $mesh['key'];
        $this->assertSame('value1', $result1);

        // Directly modify the underlying data
        $data['key'] = 'value2';

        // Memoization caches the get operation, so it still returns the cached value
        $result2 = $mesh['key'];
        $this->assertSame('value1', $result2, 'Memoization caches the get result');
    }

    public function testMemoizationWithDefaultBehavior(): void
    {
        $mesh = new Mesh();
        $mesh['computed'] = 'expensive-result';

        $mesh->memoize();

        // Should still work normally
        $result1 = $mesh['computed'];
        $result2 = $mesh['computed'];

        $this->assertSame('expensive-result', $result1);
        $this->assertSame($result1, $result2);
    }
}
