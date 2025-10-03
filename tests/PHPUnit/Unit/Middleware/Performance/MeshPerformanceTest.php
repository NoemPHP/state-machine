<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Performance;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh supports efficient array operations through middleware
 */
#[Group('middleware')]
#[Group('performance')]
class MeshPerformanceTest extends TestCase
{
    public function testMeshHandlesMultipleOperations(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $mesh = new Mesh($data);

        // Should efficiently handle multiple reads
        $this->assertEquals(1, $mesh['a']);
        $this->assertEquals(2, $mesh['b']);
        $this->assertEquals(3, $mesh['c']);

        // Should efficiently handle writes
        $mesh['d'] = 4;
        $this->assertEquals(4, $mesh['d']);

        // Should efficiently handle exists checks
        $this->assertTrue(isset($mesh['a']));
        $this->assertFalse(isset($mesh['z']));
    }

    public function testMeshWithMiddlewareExtensions(): void
    {
        $data = ['key' => 'value'];
        $mesh = new Mesh($data);

        // Extend with middleware
        $mesh->extend(
            offsetGet: function ($offset, $next) {
                return strtoupper($next($offset));
            }
        );

        // Middleware should be efficiently applied
        $result = $mesh['key'];
        $this->assertEquals('VALUE', $result);
    }

    public function testMemoizationImprovePerformance(): void
    {
        $callCount = 0;
        $data = [];
        $mesh = new Mesh(
            $data,
            offsetGet: function ($offset) use (&$callCount) {
                $callCount++;
                return "computed-{$offset}";
            }
        );

        $mesh->memoize();

        // First call should compute
        $result1 = $mesh['test'];
        $this->assertEquals('computed-test', $result1);
        $this->assertEquals(1, $callCount);

        // Second call should use memoized value
        $result2 = $mesh['test'];
        $this->assertEquals('computed-test', $result2);
        $this->assertEquals(1, $callCount); // Should not increment
    }
}
