<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Ornament prevents infinite resolution loops
 */
#[Group('async'), Group('ornament-resolution')]
class PreventsInfiniteLoopsTest extends TestCase
{
    public function testPreventsDirectSelfReference(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        
        new Ornament($mesh, 'recursive', function(OrnamentResolver $r) {
            // Attempting to access own key during resolution
            // Should not trigger infinite loop
            $self = $r->has('recursive') ? $r->get('recursive') : 'default';
            $r->resolve($self);
        });
        
        // This should not cause infinite loop
        $result = $mesh['recursive'];
        
        // Since we're in the middle of resolving, accessing self returns default
        $this->assertSame('default', $result);
    }
    
    public function testAllowsResolutionAfterCompletion(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $callCount = 0;
        
        new Ornament($mesh, 'value', function(OrnamentResolver $r) use (&$callCount) {
            $callCount++;
            $r->resolve('resolved');
        });
        
        // First resolution
        $mesh['value'];
        $this->assertSame(1, $callCount);
        
        // Multiple accesses should use cache, not trigger new resolution
        $mesh['value'];
        $mesh['value'];
        $this->assertSame(1, $callCount, 'Should not re-resolve when cached');
    }
    
    public function testResolvingFlagResets(): void
    {
        $data = ['dep' => 'v1'];
        $mesh = new Mesh($data);
        $resolverCalls = [];
        
        new Ornament($mesh, 'computed', function(OrnamentResolver $r) use (&$resolverCalls) {
            $dep = $r->get('dep');
            $resolverCalls[] = $dep;
            $r->resolve($dep . '_result');
        });
        
        // First resolution
        $mesh['computed'];
        $this->assertCount(1, $resolverCalls);
        
        // Invalidate by changing dependency
        $mesh['dep'] = 'v2';
        
        // Second resolution should work (resolving flag was reset)
        $mesh['computed'];
        $this->assertCount(2, $resolverCalls);
        $this->assertSame(['v1', 'v2'], $resolverCalls);
    }
}
