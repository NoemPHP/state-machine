<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime generates default trigger when no triggerFactory configured
 */
#[Group('runtime')]
#[Group('runtime-event-loop')]
class RuntimeDefaultTriggerGenerationTest extends TestCase
{
    public function testDefaultTriggerIsGeneratedWhenNoFactoryProvided(): void
    {
        $receivedTrigger = null;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$receivedTrigger): void {
                $receivedTrigger = $t;
            })
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig());

        $runtime->run();

        $this->assertIsObject($receivedTrigger, 'Should receive default trigger object');
    }

    public function testDefaultTriggerIsStdClass(): void
    {
        $receivedTrigger = null;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$receivedTrigger): void {
                $receivedTrigger = $t;
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertInstanceOf(\stdClass::class, $receivedTrigger, 'Default trigger should be stdClass');
    }

    public function testDefaultTriggerHasResultProperty(): void
    {
        $receivedTrigger = null;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$receivedTrigger): void {
                $receivedTrigger = $t;
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertTrue(property_exists($receivedTrigger, 'result'), 'Default trigger should have result property');
        $this->assertNull($receivedTrigger->result, 'result property should be null by default');
    }

    public function testDefaultTriggerAllowsDynamicProperties(): void
    {
        $receivedTrigger = null;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$receivedTrigger): void {
                $receivedTrigger = $t;
                $t->customProperty = 'test value';
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals('test value', $receivedTrigger->customProperty, 'Default trigger should allow dynamic properties');
    }
}
