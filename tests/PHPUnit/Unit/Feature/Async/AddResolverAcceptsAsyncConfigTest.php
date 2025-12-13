<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AddResolver;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AddResolver BuildStep accepts optional AsyncConfig parameter
 * Intent: Enables async configuration for resolvers (priority, singleton, timeout)
 */
#[Group('async'), Group('unit'), Group('resolver')]
class AddResolverAcceptsAsyncConfigTest extends TestCase
{
    public function testAddResolverAcceptsAsyncConfigParameter(): void
    {
        $callback = fn() => 'value';
        $config = new AsyncConfig(
            debounce: 0.5,
            throttle: 1.0,
            singleton: true,
            priority: Priority::HIGH,
            timeout: 5.0
        );

        $buildStep = new AddResolver('testKey', $callback, $config);

        $this->assertNotNull($buildStep);
    }

    public function testAddResolverAsyncConfigCanBeNull(): void
    {
        $callback = fn() => 'value';

        $buildStep = new AddResolver('testKey', $callback, null);

        $this->assertNotNull($buildStep);
    }
}
