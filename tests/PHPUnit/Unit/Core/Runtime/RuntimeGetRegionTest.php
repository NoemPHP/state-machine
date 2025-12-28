<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.getRegion() returns wrapped region instance
 */
#[Group('runtime')]
#[Group('runtime-queries')]
class RuntimeGetRegionTest extends TestCase
{
    public function testGetRegionReturnsWrappedRegionInstance(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region);

        $retrievedRegion = $runtime->getRegion();

        $this->assertSame($region, $retrievedRegion, 'getRegion() should return the wrapped region instance');
    }

    public function testGetRegionReturnsRegionBeforeExecution(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->build();

        $runtime = new StandardRuntime($region);

        $this->assertSame($region, $runtime->getRegion(), 'Should return region before execution');
    }

    public function testGetRegionReturnsRegionDuringExecution(): void
    {
        $regionFromGetter = null;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$runtime, &$regionFromGetter): void {
                $regionFromGetter = $runtime->getRegion();
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertSame($region, $regionFromGetter, 'Should return same region during execution');
    }

    public function testGetRegionReturnsRegionAfterCompletion(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertSame($region, $runtime->getRegion(), 'Should return region after completion');
    }

    public function testGetRegionAllowsDirectRegionManipulation(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->build();

        $runtime = new StandardRuntime($region);

        $retrievedRegion = $runtime->getRegion();

        // Can use region methods directly
        $this->assertEquals('start', $retrievedRegion->currentState());

        // Can subscribe to events
        $eventReceived = false;
        $deregister = $retrievedRegion->on(function ($event) use (&$eventReceived) {
            $eventReceived = true;
        });

        $retrievedRegion->trigger((object)['test' => true]);

        $this->assertTrue($eventReceived, 'Should be able to subscribe to region events');

        $deregister();
    }
}
