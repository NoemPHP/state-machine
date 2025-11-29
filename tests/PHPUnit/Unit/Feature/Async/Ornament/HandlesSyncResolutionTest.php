<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Ornament handles synchronous resolution
 */
#[Group('async'), Group('ornament-resolution')]
class HandlesSyncResolutionTest extends TestCase
{
    public function testDetectsSynchronousResolution(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        
        // Resolver that completes immediately
        new Ornament($mesh, 'sync', function(OrnamentResolver $r) {
            $r->resolve('immediate_value');
            // Resolution happens synchronously within the callback
        });
        
        // Should return the resolved value immediately
        $result = $mesh['sync'];
        $this->assertSame('immediate_value', $result);
    }
    
    public function testImmediatelyReturnsResultAfterSyncResolution(): void
    {
        $data = ['a' => 10];
        $mesh = new Mesh($data);
        
        new Ornament($mesh, 'computed', function(OrnamentResolver $r) {
            $a = $r->get('a');
            $r->resolve($a * 2);  // Resolve immediately
        });
        
        // First access should complete synchronously
        $result = $mesh['computed'];
        $this->assertSame(20, $result, 'Should return computed value immediately');
    }
    
    public function testSyncResolutionWithDependencies(): void
    {
        $data = ['x' => 5, 'y' => 3];
        $mesh = new Mesh($data);
        
        new Ornament($mesh, 'sum', function(OrnamentResolver $r) {
            $x = $r->get('x');
            $y = $r->get('y');
            $r->resolve($x + $y);  // Synchronous computation
        });
        
        $result = $mesh['sum'];
        $this->assertSame(8, $result);
    }
    
    public function testMultipleSyncResolversWorkIndependently(): void
    {
        $data = ['val' => 100];
        $mesh = new Mesh($data);
        
        new Ornament($mesh, 'double', function(OrnamentResolver $r) {
            $val = $r->get('val');
            $r->resolve($val * 2);
        });
        
        new Ornament($mesh, 'triple', function(OrnamentResolver $r) {
            $val = $r->get('val');
            $r->resolve($val * 3);
        });
        
        $this->assertSame(200, $mesh['double']);
        $this->assertSame(300, $mesh['triple']);
    }
    
    public function testSyncResolutionMarksAsResolved(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $callCount = 0;
        
        new Ornament($mesh, 'value', function(OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $r->resolve('resolved');
        });
        
        // First access resolves synchronously
        $mesh['value'];
        
        // Second access should use cached value (not re-resolve)
        $mesh['value'];
        
        $this->assertSame(1, $callCount, 'Sync resolution should mark as resolved');
    }
}
