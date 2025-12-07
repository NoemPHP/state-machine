<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Ornament invalidates cache when dependencies change
 */
#[Group('async'), Group('ornament-resolution')]
class InvalidatesCacheOnDependencyChangeTest extends TestCase
{
    public function testInvalidatesOnDependencySet(): void
    {
        $data = ['dep' => 'old'];
        $mesh = new Mesh($data);
        $callCount = 0;

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $dep = $r->get('dep');
            $r->resolve($dep . '_result');
        });

        // First access
        $this->assertSame('old_result', $mesh['computed']);
        $this->assertSame(1, $callCount);

        // Second access uses cache
        $this->assertSame('old_result', $mesh['computed']);
        $this->assertSame(1, $callCount);

        // Change dependency - should invalidate cache
        $mesh['dep'] = 'new';

        // Next access should recompute
        $this->assertSame('new_result', $mesh['computed']);
        $this->assertSame(2, $callCount, 'Should recompute after dependency change');
    }

    public function testInvalidatesOnDependencyUnset(): void
    {
        $data = ['dep' => 'value'];
        $mesh = new Mesh($data);
        $callCount = 0;

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $dep = $r->has('dep') ? $r->get('dep') : 'none';
            $r->resolve($dep);
        });

        // First access
        $this->assertSame('value', $mesh['computed']);
        $this->assertSame(1, $callCount);

        // Unset dependency - should invalidate cache
        unset($mesh['dep']);

        // Next access should recompute
        $this->assertSame('none', $mesh['computed']);
        $this->assertSame(2, $callCount, 'Should recompute after dependency unset');
    }

    public function testInvalidatesOnlyRelevantCache(): void
    {
        $data = ['a' => 'A', 'b' => 'B'];
        $mesh = new Mesh($data);
        $count1 = 0;
        $count2 = 0;

        new Ornament($mesh, 'computed1', function (OrnamentResolver $r) use (&$count1) {
            $count1++;
            $a = $r->get('a');
            $r->resolve($a . '1');
        });

        new Ornament($mesh, 'computed2', function (OrnamentResolver $r) use (&$count2) {
            $count2++;
            $b = $r->get('b');
            $r->resolve($b . '2');
        });

        // Access both
        $mesh['computed1'];
        $mesh['computed2'];
        $this->assertSame(1, $count1);
        $this->assertSame(1, $count2);

        // Change 'a' - should only invalidate computed1
        $mesh['a'] = 'X';

        $mesh['computed1'];  // Should recompute
        $mesh['computed2'];  // Should use cache

        $this->assertSame(2, $count1, 'computed1 should recompute');
        $this->assertSame(1, $count2, 'computed2 should not recompute');
    }

    public function testRecacheAfterInvalidation(): void
    {
        $data = ['dep' => 'v1'];
        $mesh = new Mesh($data);
        $callCount = 0;

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $dep = $r->get('dep');
            $r->resolve($dep);
        });

        // First cycle
        $mesh['computed'];
        $mesh['computed'];
        $this->assertSame(1, $callCount);

        // Invalidate
        $mesh['dep'] = 'v2';

        // Second cycle
        $mesh['computed'];
        $mesh['computed'];
        $mesh['computed'];
        $this->assertSame(2, $callCount, 'Should recache after invalidation');
    }
}
