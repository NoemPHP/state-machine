<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\DefaultCallbackType;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: AddCallback uses default callback type when type parameter is null
 *
 * Intent: Enables backward compatibility by providing sensible defaults for
 * callbacks without explicit type specification
 */
#[CoversClass(AddCallback::class)]
#[CoversClass(DefaultCallbackType::class)]
final class DefaultCallbackTypeTest extends TestCase
{
    public function testAddCallbackUsesDefaultTypeWhenNull(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback = fn() => 'test';

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callback
                    // type is null (default)
                )
            )
            ->build([]);

        $records = $registry->query();
        $this->assertCount(1, $records);
        $this->assertTrue(
            DefaultCallbackType::get()->is($records[0]->type),
            'Callback should use DefaultCallbackType when type is null'
        );
    }

    public function testDefaultCallbackTypeIsSingleton(): void
    {
        $instance1 = DefaultCallbackType::get();
        $instance2 = DefaultCallbackType::get();

        $this->assertSame($instance1, $instance2);
    }
}
