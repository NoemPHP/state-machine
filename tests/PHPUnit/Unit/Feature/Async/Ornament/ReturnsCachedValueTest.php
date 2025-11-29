<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Ornament returns cached value on repeat access
 */
#[Group('async'), Group('ornament-resolution')]
class ReturnsCachedValueTest extends TestCase
{
    public function testReturnsExactCachedValue(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        
        new Ornament($mesh, 'computed', function(OrnamentResolver $r) {
            $r->resolve('cached_value');
        });
        
        $first = $mesh['computed'];
        $second = $mesh['computed'];
        
        $this->assertSame('cached_value', $first);
        $this->assertSame('cached_value', $second);
        $this->assertSame($first, $second, 'Should return identical cached value');
    }
    
    public function testReturnsCachedObjectReference(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $object = new \stdClass();
        $object->prop = 'value';
        
        new Ornament($mesh, 'obj', function(OrnamentResolver $r) use ($object) {
            $r->resolve($object);
        });
        
        $first = $mesh['obj'];
        $second = $mesh['obj'];
        
        $this->assertSame($object, $first);
        $this->assertSame($object, $second);
        $this->assertSame($first, $second, 'Should return same object reference');
    }
    
    public function testReturnsCachedArrayValue(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        
        new Ornament($mesh, 'arr', function(OrnamentResolver $r) {
            $r->resolve(['a' => 1, 'b' => 2]);
        });
        
        $first = $mesh['arr'];
        $second = $mesh['arr'];
        
        $this->assertSame(['a' => 1, 'b' => 2], $first);
        $this->assertSame(['a' => 1, 'b' => 2], $second);
        $this->assertSame($first, $second);
    }
    
    public function testReturnsCachedNullValue(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $callCount = 0;
        
        new Ornament($mesh, 'nullable', function(OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $r->resolve(null);
        });
        
        $first = $mesh['nullable'];
        $second = $mesh['nullable'];
        
        $this->assertNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $callCount, 'Should cache null value');
    }
    
    public function testReturnsCachedFalseValue(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $callCount = 0;
        
        new Ornament($mesh, 'bool', function(OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $r->resolve(false);
        });
        
        $first = $mesh['bool'];
        $second = $mesh['bool'];
        
        $this->assertFalse($first);
        $this->assertFalse($second);
        $this->assertSame(1, $callCount, 'Should cache false value');
    }
}
