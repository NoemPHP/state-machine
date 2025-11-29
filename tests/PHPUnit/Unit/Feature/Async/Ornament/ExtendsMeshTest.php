<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Ornament extends mesh with lazy resolution middleware
 */
#[Group('async'), Group('ornament-resolution')]
class ExtendsMeshTest extends TestCase
{
    public function testExtendsMeshWithMiddleware(): void
    {
        $data = ['base' => 'value'];
        $mesh = new Mesh($data);

        // Create ornament which should extend the mesh
        new Ornament($mesh, 'computed', fn(OrnamentResolver $r) => $r->resolve('lazy'));

        // Accessing the key should trigger the resolver through the mesh middleware
        $result = $mesh['computed'];

        // After resolution, the value should be available
        $this->assertSame('lazy', $result, 'Mesh should intercept and resolve the lazy key');
    }

    public function testInterceptsOffsetGetForLazyKey(): void
    {
        $data = ['base' => 'value'];
        $mesh = new Mesh($data);
        $resolved = false;

        new Ornament($mesh, 'computed', function(OrnamentResolver $r) use (&$resolved) {
            $resolved = true;
            $r->resolve('resolved');
        });

        // Accessing the key should trigger resolution
        $result = $mesh['computed'];

        $this->assertTrue($resolved, 'Resolution should be triggered on access');
        $this->assertSame('resolved', $result);
    }

    public function testDoesNotInterceptOtherKeys(): void
    {
        $data = ['other' => 'original'];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'computed', fn(OrnamentResolver $r) => $r->resolve('lazy'));

        // Other keys should work normally
        $this->assertSame('original', $mesh['other']);
    }

    public function testInterceptsOffsetSetForDependencies(): void
    {
        $data = ['dep' => 'old'];
        $mesh = new Mesh($data);

        // Create ornament that depends on 'dep'
        new Ornament($mesh, 'computed', function(OrnamentResolver $r) {
            $value = $r->get('dep');
            $r->resolve($value . '_computed');
        });

        // First access resolves with 'old'
        $this->assertSame('old_computed', $mesh['computed']);

        // Change dependency
        $mesh['dep'] = 'new';

        // Should recompute with 'new'
        $this->assertSame('new_computed', $mesh['computed']);
    }

    public function testInterceptsOffsetUnsetForDependencies(): void
    {
        $data = ['dep' => 'value'];
        $mesh = new Mesh($data);
        
        // Create ornament that depends on 'dep'
        new Ornament($mesh, 'computed', function(OrnamentResolver $r) {
            $value = $r->has('dep') ? $r->get('dep') : 'default';
            $r->resolve($value . '_computed');
        });
        
        // First access
        $this->assertSame('value_computed', $mesh['computed']);
        
        // Unset dependency
        unset($mesh['dep']);
        
        // Should recompute without 'dep'
        $this->assertSame('default_computed', $mesh['computed']);
    }
}
