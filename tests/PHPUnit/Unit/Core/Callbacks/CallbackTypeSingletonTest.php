<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackType;
use PHPUnit\Framework\TestCase;

// Test double for singleton testing
class SingletonTestCallbackType extends CallbackType
{
}

/**
 * Test: CallbackType provides static get() method returning singleton instance
 *
 * Intent: Ensures each callback type has exactly one instance for identity comparisons
 * and efficient type checking
 */
class CallbackTypeSingletonTest extends TestCase
{
    public function testGetMethodReturnsSameInstance(): void
    {
        $instance1 = SingletonTestCallbackType::get();
        $instance2 = SingletonTestCallbackType::get();

        $this->assertSame(
            $instance1,
            $instance2,
            'get() must return the same instance on multiple calls'
        );
    }

    public function testGetMethodReturnsCallbackTypeInstance(): void
    {
        $instance = SingletonTestCallbackType::get();

        $this->assertInstanceOf(
            CallbackType::class,
            $instance,
            'get() must return an instance of CallbackType'
        );

        $this->assertInstanceOf(
            SingletonTestCallbackType::class,
            $instance,
            'get() must return an instance of the concrete type'
        );
    }

    public function testSingletonCannotBeCloned(): void
    {
        $instance = SingletonTestCallbackType::get();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to private');

        clone $instance;
    }

    public function testSingletonCannotBeUnserialized(): void
    {
        $instance = SingletonTestCallbackType::get();
        $serialized = serialize($instance);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot unserialize an instance of');

        unserialize($serialized);
    }
}
