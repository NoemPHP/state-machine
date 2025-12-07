<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Ornament caches resolved values
 */
#[Group('async'), Group('ornament-resolution')]
class CachesResolvedValuesTest extends TestCase
{
    public function testCachesResolvedValue(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $callCount = 0;

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $r->resolve('cached_value');
        });

        // First access
        $result1 = $mesh['computed'];
        $this->assertSame('cached_value', $result1);
        $this->assertSame(1, $callCount, 'Resolver should be called once');

        // Second access should use cache
        $result2 = $mesh['computed'];
        $this->assertSame('cached_value', $result2);
        $this->assertSame(1, $callCount, 'Resolver should not be called again');

        // Third access should still use cache
        $result3 = $mesh['computed'];
        $this->assertSame('cached_value', $result3);
        $this->assertSame(1, $callCount, 'Resolver should still not be called again');
    }

    public function testCachesComplexValue(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $mesh = new Mesh($data);
        $callCount = 0;

        new Ornament($mesh, 'sum', function (OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $a = $r->get('a');
            $b = $r->get('b');
            $r->resolve($a + $b);
        });

        // First access
        $this->assertSame(3, $mesh['sum']);
        $this->assertSame(1, $callCount);

        // Multiple accesses
        $this->assertSame(3, $mesh['sum']);
        $this->assertSame(3, $mesh['sum']);
        $this->assertSame(3, $mesh['sum']);
        $this->assertSame(1, $callCount, 'Should still only be called once');
    }

    public function testCacheIsIndependentPerOrnament(): void
    {
        $data = ['x' => 10];
        $mesh = new Mesh($data);
        $count1 = 0;
        $count2 = 0;

        new Ornament($mesh, 'computed1', function (OrnamentResolver $r) use (&$count1) {
            $count1++;
            $r->resolve('value1');
        });

        new Ornament($mesh, 'computed2', function (OrnamentResolver $r) use (&$count2) {
            $count2++;
            $r->resolve('value2');
        });

        // Access first ornament multiple times
        $mesh['computed1'];
        $mesh['computed1'];
        $this->assertSame(1, $count1);

        // Access second ornament multiple times
        $mesh['computed2'];
        $mesh['computed2'];
        $this->assertSame(1, $count2);

        // Each ornament maintains its own cache
        $this->assertSame('value1', $mesh['computed1']);
        $this->assertSame('value2', $mesh['computed2']);
        $this->assertSame(1, $count1);
        $this->assertSame(1, $count2);
    }
}
