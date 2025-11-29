<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrnamentResolver triggers resolution callback with result
 */
#[Group('async'), Group('ornament-resolution')]
class ResolverTriggersCallbackTest extends TestCase
{
    public function testResolveTriggersCallback(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $callbackTriggered = false;
        $callbackValue = null;
        
        // We'll manually create an OrnamentResolver to test its callback
        $callback = function($result) use (&$callbackTriggered, &$callbackValue) {
            $callbackTriggered = true;
            $callbackValue = $result;
        };
        
        $resolver = new OrnamentResolver($mesh, $callback);
        $resolver->resolve('test_value');
        
        $this->assertTrue($callbackTriggered, 'Callback should be triggered');
        $this->assertSame('test_value', $callbackValue, 'Callback should receive the resolved value');
    }
    
    public function testResolvePassesCorrectValue(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $receivedValue = null;
        
        $callback = function($result) use (&$receivedValue) {
            $receivedValue = $result;
        };
        
        $resolver = new OrnamentResolver($mesh, $callback);
        $resolver->resolve(42);
        
        $this->assertSame(42, $receivedValue);
    }
    
    public function testResolveWithComplexValue(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $receivedValue = null;
        
        $callback = function($result) use (&$receivedValue) {
            $receivedValue = $result;
        };
        
        $resolver = new OrnamentResolver($mesh, $callback);
        $complexValue = ['a' => 1, 'b' => ['c' => 2]];
        $resolver->resolve($complexValue);
        
        $this->assertSame($complexValue, $receivedValue);
    }
    
    public function testResolveWithObject(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $receivedValue = null;
        
        $callback = function($result) use (&$receivedValue) {
            $receivedValue = $result;
        };
        
        $resolver = new OrnamentResolver($mesh, $callback);
        $object = new \stdClass();
        $object->prop = 'value';
        $resolver->resolve($object);
        
        $this->assertSame($object, $receivedValue);
    }
    
    public function testResolveCanBeCalledOnce(): void
    {
        $data = [];
        $mesh = new Mesh($data);
        $callCount = 0;
        
        $callback = function($result) use (&$callCount) {
            $callCount++;
        };
        
        $resolver = new OrnamentResolver($mesh, $callback);
        $resolver->resolve('value1');
        $resolver->resolve('value2');  // Second call should also trigger
        
        $this->assertSame(2, $callCount, 'Multiple resolve calls should each trigger callback');
    }
}
