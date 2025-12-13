<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async\Resolvers;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\Priority;
use Noem\State\Feature\Async\ResolverRecord;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ResolverRecord stores optional AsyncConfig
 * Intent: Associates async configuration with resolver for priority, singleton, timeout behavior
 */
#[Group('async'), Group('unit'), Group('resolver')]
class ResolverRecordStoresAsyncConfigTest extends TestCase
{
    public function testResolverRecordStoresAsyncConfig(): void
    {
        $region = $this->createMock(Region::class);
        $callback = fn() => 'value';
        $config = new AsyncConfig(
            debounce: 0.5,
            throttle: 1.0,
            singleton: true,
            priority: Priority::HIGH,
            timeout: 5.0
        );

        $record = new ResolverRecord($region, 'testKey', $callback, $config);

        $this->assertSame($config, $record->asyncConfig);
    }

    public function testResolverRecordAsyncConfigCanBeNull(): void
    {
        $region = $this->createMock(Region::class);
        $callback = fn() => 'value';

        $record = new ResolverRecord($region, 'testKey', $callback, null);

        $this->assertNull($record->asyncConfig);
    }
}
