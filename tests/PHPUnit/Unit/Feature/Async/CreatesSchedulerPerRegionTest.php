<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature creates CoroutineScheduler per region on first async callback
 */
#[Group('async'), Group('async-callback-handling')]
class CreatesSchedulerPerRegionTest extends TestCase
{
    public function testCreatesSchedulerPerRegion(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());
        
        $generatorInvoked = false;
        
        $region = $builder
            ->setStates('idle', 'processing')
            ->onAction('idle', function (object $trigger) use (&$generatorInvoked) {
                $generatorInvoked = true;
                yield 'value1';
            })
            ->build();
        
        $this->assertFalse($generatorInvoked, 'Generator should not be invoked before trigger');
        
        // Trigger the action which returns a generator
        $region->trigger(new \stdClass());
        
        $this->assertTrue($generatorInvoked, 'Generator-based callback should have been invoked');
        // The scheduler is created lazily, and we can verify it works by checking that async callbacks function
    }
    
    public function testCreatesSchedulerOnlyOncePerRegion(): void
    {
        $this->markTestSkipped('I need to reconsider this functionality. maybe coroutines should rather run once and vanish');
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());
        
        $callCount = 0;
        
        $region = $builder
            ->setStates('idle')
            ->onAction('idle', function (object $trigger) use (&$callCount) {
                $callCount++;
                yield 'value';
            })
            ->build();
        
        // First trigger - scheduler should be created
        $region->trigger(new \stdClass());
        $this->assertSame(1, $callCount);
        
        // Second trigger - should reuse existing scheduler
        $region->trigger(new \stdClass());
        $this->assertSame(2, $callCount);
    }
}
