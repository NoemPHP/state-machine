<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Ornament tracks dependencies during resolution
 */
#[Group('async'), Group('ornament-resolution')]
class TracksDependenciesTest extends TestCase
{
    public function testTracksSingleDependency(): void
    {
        $data = ['a' => 'value_a'];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) {
            $a = $r->get('a'); // This should be tracked as dependency
            $r->resolve($a . '_computed');
        });

        // First access
        $this->assertSame('value_a_computed', $mesh['computed']);

        // Changing the dependency should invalidate cache
        $mesh['a'] = 'new_value_a';

        // Should recompute with new value
        $this->assertSame('new_value_a_computed', $mesh['computed']);
    }

    public function testTracksMultipleDependencies(): void
    {
        $data = ['a' => 'A', 'b' => 'B'];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) {
            $a = $r->get('a');
            $b = $r->get('b');
            $r->resolve($a . $b);
        });

        // First access
        $this->assertSame('AB', $mesh['computed']);

        // Changing first dependency
        $mesh['a'] = 'X';
        $this->assertSame('XB', $mesh['computed']);

        // Changing second dependency
        $mesh['b'] = 'Y';
        $this->assertSame('XY', $mesh['computed']);
    }

    public function testOnlyTracksAccessedDependencies(): void
    {
        $data = ['a' => 'A', 'b' => 'B', 'c' => 'C'];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) {
            $a = $r->get('a');
            // 'b' is not accessed
            $r->resolve($a . '_result');
        });

        // First access
        $this->assertSame('A_result', $mesh['computed']);

        // Changing 'b' should NOT invalidate cache
        $mesh['b'] = 'B_new';
        $this->assertSame('A_result', $mesh['computed'], 'Untracked dependency change should not invalidate');

        // Changing 'a' SHOULD invalidate cache
        $mesh['a'] = 'A_new';
        $this->assertSame('A_new_result', $mesh['computed'], 'Tracked dependency change should invalidate');
    }

    public function testTracksDependenciesWithHasCheck(): void
    {
        $data = ['a' => 'value'];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) {
            // Using has() should also track the dependency
            $result = $r->has('a') ? $r->get('a') : 'default';
            $r->resolve($result);
        });

        // First access
        $this->assertSame('value', $mesh['computed']);

        // Changing dependency
        $mesh['a'] = 'new_value';
        $this->assertSame('new_value', $mesh['computed']);
    }
}
