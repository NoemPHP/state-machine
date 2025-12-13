<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackType;
use Noem\State\MetaType;
use PHPUnit\Framework\TestCase;

// Test double for testing CallbackType
class TestCallbackType extends CallbackType
{
}

/**
 * Test: CallbackType extends MetaType for singleton pattern
 *
 * Intent: Provides type-safe singleton instances for callback channels, enabling features
 * to define custom callback types (async, sync, priority, etc.) with identity semantics
 */
class CallbackTypeExtendsMetaTypeTest extends TestCase
{
    public function testCallbackTypeExtendsMetaType(): void
    {
        $this->assertTrue(
            is_subclass_of(CallbackType::class, MetaType::class),
            'CallbackType must extend MetaType for singleton pattern'
        );
    }

    public function testCallbackTypeIsAbstract(): void
    {
        $reflection = new \ReflectionClass(CallbackType::class);
        $this->assertTrue(
            $reflection->isAbstract(),
            'CallbackType must be abstract to force concrete implementations'
        );
    }

    public function testCallbackTypeInheritsMetaTypeBehavior(): void
    {
        $this->assertTrue(
            method_exists(TestCallbackType::class, 'get'),
            'CallbackType inherits get() method from MetaType'
        );

        $this->assertTrue(
            method_exists(TestCallbackType::class, 'is'),
            'CallbackType inherits is() method from MetaType'
        );

        $this->assertTrue(
            method_exists(TestCallbackType::class, 'key'),
            'CallbackType inherits key() method from MetaType'
        );

        // Verify singleton behavior works
        $instance1 = TestCallbackType::get();
        $instance2 = TestCallbackType::get();
        $this->assertSame($instance1, $instance2, 'CallbackType instances are singletons');
    }
}
