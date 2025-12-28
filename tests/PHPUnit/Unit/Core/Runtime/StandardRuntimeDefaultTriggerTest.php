<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: StandardRuntime generates anonymous object trigger by default
 */
#[Group('runtime')]
#[Group('standard-runtime')]
class StandardRuntimeDefaultTriggerTest extends TestCase
{
    public function testGeneratesAnonymousObjectByDefault(): void
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

        $this->assertIsObject($receivedTrigger, 'Default trigger should be an object');
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
    }

    public function testDefaultTriggerAllowsDynamicProperties(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t): void {
                // Should be able to add dynamic properties
                $t->customData = 'test';
                $t->anotherProperty = 123;
            })
            ->build();

        $runtime = new StandardRuntime($region);

        // Should not throw
        $runtime->run();

        $this->assertTrue(true, 'Default trigger should allow dynamic properties');
    }

    public function testDefaultTriggerCompatibleWithHolonBehavior(): void
    {
        // StandardRuntime should generate triggers compatible with existing Holon implementation
        $triggers = [];

        $region = (new RegionBuilder())
            ->setStates('collecting', 'done')
            ->markInitial('collecting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('collecting', 'done', function (object $t) use (&$triggers): bool {
                static $count = 0;
                $triggers[] = $t;
                $count++;
                return $count >= 2;
            }))
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        foreach ($triggers as $trigger) {
            $this->assertInstanceOf(\stdClass::class, $trigger);
            $this->assertTrue(property_exists($trigger, 'result'));
        }
    }

    public function testDefaultTriggerResultPropertyInitiallyNull(): void
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

        $this->assertNull($receivedTrigger->result, 'result property should be null by default');
    }
}
