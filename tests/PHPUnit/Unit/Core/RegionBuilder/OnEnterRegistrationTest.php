<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can register enter handlers using onEnter
 */
#[Group('region-builder')]
#[Group('event-handler-registration')]
class OnEnterRegistrationTest extends TestCase
{
    public function testOnEnterAcceptsStateAndClosure(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');

        $handler = function (object $trigger): void {
        };

        $result = $builder->onEnter('processing', $handler);

        $this->assertSame($builder, $result, 'onEnter should return builder for chaining');
    }

    public function testOnEnterHandlerIsInvokedWhenEnteringState(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing')
                ->markInitial('idle');

        $enterCalled = false;
        $receivedTrigger = null;

        $builder->onEnter('processing', function (object $trigger) use (&$enterCalled, &$receivedTrigger): void {
            $enterCalled = true;
            $receivedTrigger = $trigger;
        });

        $builder->addBuildStep(new AddTransition('idle', 'processing', fn(object $t): bool => true));

        $region = $builder->build();

        $this->assertFalse($enterCalled, 'Handler should not be called before transition');

        $trigger = (object)['data' => 'test'];
        $region->trigger($trigger);

        $this->assertTrue($enterCalled, 'onEnter handler should be called when entering processing state');
        $this->assertSame($trigger, $receivedTrigger, 'Handler should receive trigger object');
    }
}
